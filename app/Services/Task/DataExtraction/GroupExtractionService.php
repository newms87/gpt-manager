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
use Newms87\Danx\Traits\HasDebugLogging;
use App\Traits\MergesExtractionResults;
use Exception;
use Illuminate\Support\Collection;

class GroupExtractionService
{
    use HasDebugLogging;
    use MergesExtractionResults;

    /**
     * Extract data from artifacts using batched processing.
     *
     * Both skim and exhaustive modes use the same batch loop:
     * - Skim mode ($stopOnConfidence = true): breaks early when all fields have sufficient confidence
     * - Exhaustive mode ($stopOnConfidence = false): processes all batches without early stopping
     *
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @param  bool  $stopOnConfidence  When true, stops processing when all fields have high confidence (skim mode)
     * @return array{data: array, page_sources: array}
     */
    public function extract(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $group,
        Collection $artifacts,
        TeamObject $teamObject,
        ?Collection $allArtifacts = null,
        bool $stopOnConfidence = false
    ): array {
        $config              = $taskRun->taskDefinition->task_runner_config;
        $confidenceThreshold = $config['confidence_threshold'] ?? 3;
        $batchSize           = $config['batch_size']           ?? 5;

        $extractedData         = [];
        $cumulativeConfidence  = [];
        $cumulativePageSources = [];

        static::logDebug('Starting extraction', [
            'artifact_count'       => $artifacts->count(),
            'stop_on_confidence'   => $stopOnConfidence,
            'confidence_threshold' => $stopOnConfidence ? $confidenceThreshold : 'N/A',
            'batch_size'           => $batchSize,
        ]);

        // Process artifacts in batches
        foreach ($artifacts->chunk($batchSize) as $batchIndex => $batch) {
            static::logDebug("Processing batch $batchIndex with " . $batch->count() . ' artifacts');

            $batchResult = $this->runExtractionOnArtifacts($taskRun, $taskProcess, $group, $batch, $teamObject, $stopOnConfidence, $allArtifacts);

            // Merge batch data with cumulative data (preserving non-null values from earlier batches)
            // and track which fields were actually updated
            $mergeResult   = $this->mergeExtractionResultsWithTracking($extractedData, $batchResult['data']);
            $extractedData = $mergeResult['merged'];

            // Only merge page sources for fields that were actually updated
            // This prevents later batches with empty data from overwriting page_sources
            $batchPageSources      = $batchResult['page_sources'] ?? [];
            $cumulativePageSources = $this->mergePageSourcesForUpdatedFields(
                $cumulativePageSources,
                $batchPageSources,
                $mergeResult['updated_fields']
            );

            // Update confidence scores (take the highest confidence for each field)
            foreach ($batchResult['confidence'] ?? [] as $field => $score) {
                if (!isset($cumulativeConfidence[$field]) || $score > $cumulativeConfidence[$field]) {
                    $cumulativeConfidence[$field] = $score;
                }
            }

            // Check if we should stop early (skim mode only)
            if ($stopOnConfidence && $this->allFieldsHaveHighConfidence($group, $cumulativeConfidence, $confidenceThreshold)) {
                $highConfidenceFields = array_filter($cumulativeConfidence, fn($score) => $score >= $confidenceThreshold);
                static::logDebug('Stopping early - all fields have sufficient confidence', [
                    'batches_processed'      => $batchIndex + 1,
                    'high_confidence_fields' => array_keys($highConfidenceFields),
                    'confidence_scores'      => $cumulativeConfidence,
                ]);
                break;
            }
        }

        return ['data' => $extractedData, 'page_sources' => $cumulativePageSources];
    }

    /**
     * Run extraction on a set of artifacts using LLM.
     *
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     */
    public function runExtractionOnArtifacts(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $group,
        Collection $artifacts,
        TeamObject $teamObject,
        bool $includeConfidence,
        ?Collection $allArtifacts = null
    ): array {
        // Expand artifacts with context pages if configured and enabled
        $config             = $taskRun->taskDefinition->task_runner_config ?? [];
        $enableContextPages = $config['enable_context_pages']              ?? false;
        $contextBefore      = $config['classification_context_before']     ?? 0;
        $contextAfter       = $config['classification_context_after']      ?? 0;

        if ($enableContextPages && ($contextBefore > 0 || $contextAfter > 0) && $allArtifacts !== null) {
            $contextService = app(ContextWindowService::class);

            // Validate that File Organization has been run (belongs_to_previous exists)
            $contextService->validateContextPagesAvailable($artifacts);

            // Use adjacency threshold from config or default
            $adjacencyThreshold = $config['adjacency_threshold'] ?? ContextWindowService::DEFAULT_ADJACENCY_THRESHOLD;

            $artifacts = $contextService->expandWithContext(
                $artifacts,
                $allArtifacts,
                $contextBefore,
                $contextAfter,
                $adjacencyThreshold
            );

            static::logDebug('Expanded artifacts with context', [
                'target_count'        => $contextService->getTargetCount($artifacts),
                'context_count'       => $contextService->getContextCount($artifacts),
                'total_count'         => $artifacts->count(),
                'adjacency_threshold' => $adjacencyThreshold,
            ]);
        }

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
        // Enable null values so LLM can return null instead of placeholder strings like "<null>"
        $jsonSchemaService = app(JsonSchemaService::class)->includeNullValues();
        $fragmentSchema    = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Get the fields from fragment selector for page source tracking
        $extractableFields = $this->getExtractableFieldsFromFragmentSelector($fragmentSelector);

        // Build response schema with top-level page_sources
        $responseSchema = $this->buildResponseSchema($fragmentSchema, $extractableFields, $includeConfidence);

        // Create a temporary in-memory SchemaDefinition with the enhanced fragment schema
        // Use hash for uniqueness - OpenAI has 64 char limit on schema names
        $groupHash            = substr(md5($group['name'] ?? 'unknown'), 0, 8);
        $tempSchemaDefinition = new SchemaDefinition([
            'name'   => 'group-extraction-' . $groupHash,
            'schema' => $responseSchema,
        ]);

        // Build extraction prompt with page source instructions
        $prompt = $this->buildExtractionPrompt($group, $teamObject, $fragmentSelector, $includeConfidence, $artifacts, $config);

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

        // Run extraction with the response schema
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

        // Extract page_sources from top-level (new pattern)
        $pageSourceService = app(PageSourceService::class);
        $pageSources       = $pageSourceService->extractPageSources($result);

        // Separate data from confidence if present
        if ($includeConfidence && isset($result['confidence'])) {
            return [
                'data'         => $result['data']       ?? [],
                'confidence'   => $result['confidence'] ?? [],
                'page_sources' => $pageSources,
            ];
        }

        return ['data' => $result['data'] ?? $result, 'confidence' => [], 'page_sources' => $pageSources];
    }

    /**
     * Build the response schema with top-level page_sources.
     *
     * Structure:
     * {
     *   "data": { ...fragmentSchema... },
     *   "page_sources": { "field": 1, ... },
     *   "confidence": { "field": 4, ... }  // optional
     * }
     *
     * @param  array<string>  $fieldNames  Fields that should have page source tracking
     */
    protected function buildResponseSchema(array $fragmentSchema, array $fieldNames, bool $includeConfidence): array
    {
        $pageSourceService = app(PageSourceService::class);

        // Build $defs with pageSource definition
        $defs = ['pageSource' => $pageSourceService->getPageSourceDef()];

        $responseSchema = [
            'type'       => 'object',
            'properties' => [
                'data'         => $fragmentSchema,
                'page_sources' => $pageSourceService->buildPageSourcesSchema($fieldNames),
            ],
            'required' => ['data', 'page_sources'],
            '$defs'    => $defs,
        ];

        // Add confidence schema when requested
        if ($includeConfidence) {
            $responseSchema['properties']['confidence'] = $this->buildConfidenceSchema($fieldNames);
            $responseSchema['required'][]               = 'confidence';
        }

        return $responseSchema;
    }

    /**
     * Build the confidence schema for per-field confidence tracking.
     *
     * @param  array<string>  $fieldNames
     */
    protected function buildConfidenceSchema(array $fieldNames): array
    {
        $properties = [];

        foreach ($fieldNames as $field) {
            $properties[$field] = [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ];
        }

        // Load description from shared external file
        $description = trim(file_get_contents(resource_path('prompts/extract-data/confidence-rating-instructions.md')));

        return [
            'type'                 => 'object',
            'description'          => $description,
            'properties'           => $properties,
            'additionalProperties' => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ],
        ];
    }

    /**
     * Build extraction prompt for LLM.
     *
     * @param  array|null  $config  Task runner config containing extraction_instructions
     */
    public function buildExtractionPrompt(
        array $group,
        TeamObject $teamObject,
        array $fragmentSelector,
        bool $includeConfidence,
        ?Collection $artifacts = null,
        ?array $config = null
    ): string {
        $template = file_get_contents(resource_path('prompts/extract-data/remaining-field-extraction.md'));

        $groupName = $group['name'] ?? 'data';

        // Build additional instructions section
        $additionalInstructions = '';
        $extractionInstructions = $config['extraction_instructions'] ?? null;
        if ($extractionInstructions) {
            $additionalInstructions = "## Additional Instructions\n{$extractionInstructions}\n\n";
        }

        // Build context instructions section
        $contextInstructions = '';
        if ($artifacts !== null) {
            $contextInstructions = app(ContextWindowService::class)->buildContextPromptInstructions($artifacts);
        }

        // Build existing data section
        $existingDataSection = '';
        $existingData        = $this->getExistingObjectData($teamObject);
        if (!empty($existingData)) {
            $existingDataSection = "## Existing Object Data\n\n";
            $existingDataSection .= "Type: {$teamObject->type}\n";
            $existingDataSection .= "Name: {$teamObject->name}\n\n";
            $existingDataSection .= json_encode($existingData, JSON_PRETTY_PRINT) . "\n\n";
        }

        // Build page source instructions section
        $pageSourceInstructions = '';
        if ($artifacts !== null) {
            $pageSourceInstructionsText = app(PageSourceService::class)->buildPageSourceInstructions($artifacts);
            if ($pageSourceInstructionsText !== '') {
                $pageSourceInstructions = '- ' . str_replace("\n", "\n- ", $pageSourceInstructionsText) . "\n";
            }
        }

        // Build confidence scale section
        $confidenceScale = '';
        if ($includeConfidence) {
            $confidenceScale = "- Rate your confidence (1-5) for each extracted field:\n";
            $confidenceScale .= "  1 = Very uncertain, likely incorrect\n";
            $confidenceScale .= "  2 = Uncertain, might be incorrect\n";
            $confidenceScale .= "  3 = Moderately confident, probably correct\n";
            $confidenceScale .= "  4 = Confident, very likely correct\n";
            $confidenceScale .= "  5 = Highly confident, definitely correct\n";
        }

        // Build response format section
        $responseFormat = "RESPOND WITH JSON:\n```json\n{\n";
        $responseFormat .= "  \"data\": { extracted fields },\n";
        $responseFormat .= '  "page_sources": { "field_name": page_number, ... }';
        if ($includeConfidence) {
            $responseFormat .= ",\n  \"confidence\": { \"field_name\": score, ... }";
        }
        $responseFormat .= "\n}\n```";

        return strtr($template, [
            '{{group_name}}'               => $groupName,
            '{{additional_instructions}}'  => $additionalInstructions,
            '{{context_instructions}}'     => $contextInstructions,
            '{{existing_data}}'            => $existingDataSection,
            '{{page_source_instructions}}' => $pageSourceInstructions,
            '{{confidence_scale}}'         => $confidenceScale,
            '{{response_format}}'          => $responseFormat,
        ]);
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
}
