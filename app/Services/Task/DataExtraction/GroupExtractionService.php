<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Support\Collection;

class GroupExtractionService
{
    use HasDebugLogging;

    /**
     * Extract data using skim mode.
     * Process artifacts in batches, stopping early when all fields have sufficient confidence.
     *
     * @return array{data: array, page_sources: array}
     */
    public function extractWithSkimMode(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $group,
        Collection $artifacts,
        TeamObject $teamObject
    ): array {
        $config              = $taskRun->taskDefinition->task_runner_config;
        $confidenceThreshold = $config['confidence_threshold'] ?? 3;
        $batchSize           = $config['skim_batch_size']      ?? 5;

        $extractedData         = [];
        $cumulativeConfidence  = [];
        $cumulativePageSources = [];

        static::logDebug('Starting skim mode extraction', [
            'artifact_count'       => $artifacts->count(),
            'confidence_threshold' => $confidenceThreshold,
            'batch_size'           => $batchSize,
        ]);

        // Process artifacts in batches
        foreach ($artifacts->chunk($batchSize) as $batchIndex => $batch) {
            static::logDebug("Processing batch $batchIndex with " . $batch->count() . ' artifacts');

            $batchResult = $this->runExtractionOnArtifacts($taskRun, $taskProcess, $group, $batch, $teamObject, true);

            // Merge batch data with cumulative data
            $extractedData = array_replace_recursive($extractedData, $batchResult['data']);

            // Merge page sources (later batches override earlier ones)
            $cumulativePageSources = array_merge($cumulativePageSources, $batchResult['page_sources'] ?? []);

            // Update confidence scores (take the highest confidence for each field)
            foreach ($batchResult['confidence'] ?? [] as $field => $score) {
                if (!isset($cumulativeConfidence[$field]) || $score > $cumulativeConfidence[$field]) {
                    $cumulativeConfidence[$field] = $score;
                }
            }

            // Check if all expected fields have high enough confidence
            if ($this->allFieldsHaveHighConfidence($group, $cumulativeConfidence, $confidenceThreshold)) {
                $highConfidenceFields = array_filter($cumulativeConfidence, fn($score) => $score >= $confidenceThreshold);
                static::logDebug('Skim mode: stopping early - all fields have sufficient confidence', [
                    'batches_processed'       => $batchIndex + 1,
                    'high_confidence_fields'  => array_keys($highConfidenceFields),
                    'confidence_scores'       => $cumulativeConfidence,
                ]);
                break;
            }
        }

        return ['data' => $extractedData, 'page_sources' => $cumulativePageSources];
    }

    /**
     * Extract data using exhaustive mode.
     * Process all artifacts and aggregate results.
     *
     * @return array{data: array, page_sources: array}
     */
    public function extractExhaustive(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $group,
        Collection $artifacts,
        TeamObject $teamObject
    ): array {
        static::logDebug('Starting exhaustive mode extraction', [
            'artifact_count' => $artifacts->count(),
        ]);

        $result = $this->runExtractionOnArtifacts($taskRun, $taskProcess, $group, $artifacts, $teamObject, false);

        return ['data' => $result['data'], 'page_sources' => $result['page_sources'] ?? []];
    }

    /**
     * Run extraction on a set of artifacts using LLM.
     */
    public function runExtractionOnArtifacts(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $group,
        Collection $artifacts,
        TeamObject $teamObject,
        bool $includeConfidence
    ): array {
        static::logDebug('Running extraction on artifacts', [
            'artifact_count'     => $artifacts->count(),
            'include_confidence' => $includeConfidence,
        ]);

        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent) {
            throw new Exception('Agent not found for TaskRun: ' . $taskRun->id);
        }

        if (!$schemaDefinition) {
            throw new Exception('SchemaDefinition not found for TaskRun: ' . $taskRun->id);
        }

        // Get the fragment selector for this group
        $fragmentSelector = $group['fragment_selector'] ?? null;

        if (!$fragmentSelector) {
            static::logDebug('No fragment selector found for group');

            return ['data' => [], 'confidence' => [], 'page_sources' => []];
        }

        // Build schema from fragment selector
        $jsonSchemaService = app(JsonSchemaService::class);
        $fragmentSchema    = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Get the fields from fragment selector for page source tracking
        $extractableFields = $this->getExtractableFieldsFromFragmentSelector($fragmentSelector);

        // Inject __source__ properties and add $defs for page source tracking
        $fragmentSchema = $this->injectPageSourceSchema($fragmentSchema, $extractableFields);

        // Create a temporary in-memory SchemaDefinition with the enhanced fragment schema
        // This ensures only the fields specified in fragment_selector are requested from the LLM
        $tempSchemaDefinition = new SchemaDefinition([
            'name'   => 'group-extraction-' . ($group['name'] ?? 'unknown'),
            'schema' => $fragmentSchema,
        ]);

        // Build extraction prompt with page source instructions
        $prompt = $this->buildExtractionPrompt($group, $teamObject, $fragmentSelector, $includeConfidence, $artifacts);

        // Create agent thread with artifacts
        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Group Data Extraction')
            ->withArtifacts($artifacts, new ArtifactFilter(
                includeFiles: false,
                includeJson: false,
                includeMeta: false
            ))
            ->withMessage($prompt)
            ->build();

        // Get timeout from config (default 5 minutes for large extractions)
        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 300;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Run extraction with the filtered fragment schema (not the full schema)
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($tempSchemaDefinition, null, $jsonSchemaService)
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Thread run failed', [
                'error' => $threadRun->error ?? 'Unknown error',
            ]);

            return ['data' => [], 'confidence' => [], 'page_sources' => []];
        }

        // Parse response using getJsonContent() method
        $result = $threadRun->lastMessage?->getJsonContent();

        if (!$result || !is_array($result)) {
            static::logDebug('Failed to get JSON content from response');

            return ['data' => [], 'confidence' => [], 'page_sources' => []];
        }

        // Extract page sources BEFORE cleaning the data (while __source__ fields still exist)
        $pageSourceService = app(PageSourceService::class);
        $pageSources       = $pageSourceService->extractPageSources($result);

        // Remove __source__ fields from the result
        $cleanedResult = $pageSourceService->removeSourceFields($result);

        // Separate data from confidence if present
        if ($includeConfidence && isset($cleanedResult['confidence'])) {
            return [
                'data'         => $cleanedResult['data']       ?? $cleanedResult,
                'confidence'   => $cleanedResult['confidence'] ?? [],
                'page_sources' => $pageSources,
            ];
        }

        return ['data' => $cleanedResult, 'confidence' => [], 'page_sources' => $pageSources];
    }

    /**
     * Build extraction prompt for LLM.
     */
    public function buildExtractionPrompt(
        array $group,
        TeamObject $teamObject,
        array $fragmentSelector,
        bool $includeConfidence,
        ?Collection $artifacts = null
    ): string {
        $groupName = $group['name'] ?? 'data';

        // Get existing object data
        $existingData = $this->getExistingObjectData($teamObject);

        $prompt = "You are extracting $groupName data from documents into a structured format.\n\n";

        // Include existing object data if any
        if (!empty($existingData)) {
            $prompt .= "EXISTING OBJECT DATA:\n";
            $prompt .= "Type: {$teamObject->type}\n";
            $prompt .= "Name: {$teamObject->name}\n";
            $prompt .= json_encode($existingData, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "- Extract the requested fields from the provided documents\n";
        $prompt .= "- If a field cannot be found, set it to null\n";
        $prompt .= "- Merge with existing data where appropriate (update or append as needed)\n";

        // Add page source instructions if artifacts are provided
        if ($artifacts !== null) {
            $pageSourceInstructions = app(PageSourceService::class)->buildPageSourceInstructions($artifacts);
            if ($pageSourceInstructions !== '') {
                $prompt .= '- ' . str_replace("\n", "\n- ", $pageSourceInstructions) . "\n";
            }
        }

        if ($includeConfidence) {
            $prompt .= "- Rate your confidence (1-5) for each extracted field:\n";
            $prompt .= "  1 = Very uncertain, likely incorrect\n";
            $prompt .= "  2 = Uncertain, might be incorrect\n";
            $prompt .= "  3 = Moderately confident, probably correct\n";
            $prompt .= "  4 = Confident, very likely correct\n";
            $prompt .= "  5 = Highly confident, definitely correct\n\n";

            $prompt .= "RESPOND WITH JSON:\n";
            $prompt .= "{\n";
            $prompt .= "  \"data\": { extracted fields },\n";
            $prompt .= "  \"confidence\": { \"field_name\": score, ... }\n";
            $prompt .= "}\n";
        } else {
            $prompt .= "\nExtract all instances of the requested data found in the documents.\n";
        }

        return $prompt;
    }

    /**
     * Get existing data from TeamObject and its attributes.
     */
    public function getExistingObjectData(TeamObject $teamObject): array
    {
        $data = [
            'id'   => $teamObject->id,
            'type' => $teamObject->type,
            'name' => $teamObject->name,
        ];

        if ($teamObject->date) {
            $data['date'] = $teamObject->date->toDateString();
        }

        if ($teamObject->description) {
            $data['description'] = $teamObject->description;
        }

        if ($teamObject->url) {
            $data['url'] = $teamObject->url;
        }

        // Load attributes
        $attributes = $teamObject->attributes()->get();

        foreach ($attributes as $attribute) {
            $data[$attribute->name] = $attribute->json_value ?? $attribute->text_value;
        }

        return $data;
    }

    /**
     * Check if all expected fields have high enough confidence.
     */
    public function allFieldsHaveHighConfidence(array $group, array $confidenceScores, int $threshold): bool
    {
        // Get expected fields from the group's fragment selector
        $expectedFields = $this->getExpectedFieldsFromGroup($group);

        if (empty($expectedFields)) {
            // If we can't determine expected fields, continue processing
            return false;
        }

        $lowConfidenceFields = [];

        foreach ($expectedFields as $field) {
            // If field is missing or below threshold, return false
            if (!isset($confidenceScores[$field]) || $confidenceScores[$field] < $threshold) {
                $lowConfidenceFields[] = $field;
            }
        }

        if (!empty($lowConfidenceFields)) {
            static::logDebug('Fields below confidence threshold', [
                'fields'    => $lowConfidenceFields,
                'threshold' => $threshold,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Get list of expected field names from group's fragment selector.
     */
    public function getExpectedFieldsFromGroup(array $group): array
    {
        $fragmentSelector = $group['fragment_selector'] ?? null;

        if (!$fragmentSelector || !isset($fragmentSelector['children'])) {
            return [];
        }

        return array_keys($fragmentSelector['children']);
    }

    /**
     * Update TeamObject with extracted data using the mapper.
     */
    public function updateTeamObjectWithExtractedData(TaskRun $taskRun, TeamObject $teamObject, array $extractedData, array $group): void
    {
        static::logDebug('Updating TeamObject with extracted data', [
            'object_id'    => $teamObject->id,
            'object_type'  => $teamObject->type,
            'object_name'  => $teamObject->name,
            'fields_count' => count($extractedData),
        ]);

        $mapper = app(JSONSchemaDataToDatabaseMapper::class);

        // Set context
        if ($taskRun->taskDefinition->schemaDefinition) {
            $mapper->setSchemaDefinition($taskRun->taskDefinition->schemaDefinition);
        }

        if ($teamObject->root_object_id) {
            $rootObject = TeamObject::find($teamObject->root_object_id);
            if ($rootObject) {
                $mapper->setRootObject($rootObject);
            }
        }

        // Update TeamObject - merge new data with existing
        $mapper->updateTeamObject($teamObject, $extractedData);

        static::logDebug('TeamObject updated successfully');
    }

    /**
     * Get extractable field names from fragment selector (leaf-level scalar fields).
     *
     * @return array<string>
     */
    protected function getExtractableFieldsFromFragmentSelector(array $fragmentSelector): array
    {
        $fields   = [];
        $children = $fragmentSelector['children'] ?? [];

        foreach ($children as $key => $child) {
            $childType = $child['type'] ?? 'object';

            // Scalar types are extractable fields
            if (!in_array($childType, ['object', 'array'], true)) {
                $fields[] = $key;
            } else {
                // Recursively check nested objects/arrays for more fields
                $nestedFields = $this->getExtractableFieldsFromFragmentSelector($child);
                $fields       = array_merge($fields, $nestedFields);
            }
        }

        return $fields;
    }

    /**
     * Inject __source__ properties and $defs into the schema for page source tracking.
     */
    protected function injectPageSourceSchema(array $schema, array $fieldNames): array
    {
        if (empty($fieldNames)) {
            return $schema;
        }

        // Add $defs with pageSource definition
        $schema['$defs'] = array_merge(
            $schema['$defs'] ?? [],
            ['pageSource' => app(PageSourceService::class)->getPageSourceDef()]
        );

        // Inject __source__ properties into the schema
        $schema = $this->injectPageSourcePropertiesRecursive($schema, $fieldNames);

        return $schema;
    }

    /**
     * Recursively inject __source__ properties into schema for each field.
     */
    protected function injectPageSourcePropertiesRecursive(array $schema, array $fieldNames): array
    {
        $type = $schema['type'] ?? 'object';

        if ($type === 'array') {
            // Handle array items
            if (isset($schema['items'])) {
                $schema['items'] = $this->injectPageSourcePropertiesRecursive($schema['items'], $fieldNames);
            }
        } elseif ($type === 'object' && isset($schema['properties'])) {
            // Inject __source__ properties for matching fields
            $schema['properties'] = app(PageSourceService::class)->injectPageSourceProperties($schema['properties'], $fieldNames);

            // Recursively process nested objects
            foreach ($schema['properties'] as $key => $propSchema) {
                if (str_starts_with($key, '__source__')) {
                    continue; // Skip already injected source properties
                }

                $propType = $propSchema['type'] ?? null;
                if (in_array($propType, ['object', 'array'], true)) {
                    $schema['properties'][$key] = $this->injectPageSourcePropertiesRecursive($propSchema, $fieldNames);
                }
            }
        }

        return $schema;
    }
}
