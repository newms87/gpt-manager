<?php

namespace App\Services\Task\DataExtraction;

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
use Illuminate\Support\Str;

class GroupExtractionService
{
    use HasDebugLogging;

    /**
     * Get classified artifacts for a specific extraction group.
     */
    public function getClassifiedArtifactsForGroup(TaskRun $taskRun, array $group): Collection
    {
        $groupKey = $group['key'] ?? ($group['name'] ? Str::snake($group['name']) : null);

        if (!$groupKey) {
            static::logDebug('No group key found for artifact classification');

            return collect();
        }

        // Get the parent output artifact and its classified children
        $parentArtifact = $taskRun->outputArtifacts()->whereNull('parent_artifact_id')->first();
        if (!$parentArtifact) {
            static::logDebug('No parent output artifact found');

            return collect();
        }

        $classificationService = app(ClassificationExecutorService::class);
        $allArtifacts          = $parentArtifact->children;

        return $classificationService->getArtifactsForCategory($allArtifacts, $groupKey);
    }

    /**
     * Extract data using skim mode.
     * Process artifacts in batches, stopping early when all fields have sufficient confidence.
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

        $extractedData        = [];
        $cumulativeConfidence = [];

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

        return $extractedData;
    }

    /**
     * Extract data using exhaustive mode.
     * Process all artifacts and aggregate results.
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

        return $result['data'];
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

            return ['data' => [], 'confidence' => []];
        }

        // Build schema from fragment selector
        $jsonSchemaService = app(JsonSchemaService::class);
        $fragmentSchema    = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Build extraction prompt
        $prompt = $this->buildExtractionPrompt($group, $teamObject, $fragmentSelector, $includeConfidence);

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

        // Get timeout from config
        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 60;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Run extraction
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($schemaDefinition, null, $jsonSchemaService)
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Thread run failed', [
                'error' => $threadRun->error ?? 'Unknown error',
            ]);

            return ['data' => [], 'confidence' => []];
        }

        // Parse response using getJsonContent() method
        $result = $threadRun->lastMessage?->getJsonContent();

        if (!$result || !is_array($result)) {
            static::logDebug('Failed to get JSON content from response');

            return ['data' => [], 'confidence' => []];
        }

        // Separate data from confidence if present
        if ($includeConfidence && isset($result['confidence'])) {
            return [
                'data'       => $result['data']       ?? $result,
                'confidence' => $result['confidence'] ?? [],
            ];
        }

        return ['data' => $result, 'confidence' => []];
    }

    /**
     * Build extraction prompt for LLM.
     */
    public function buildExtractionPrompt(array $group, TeamObject $teamObject, array $fragmentSelector, bool $includeConfidence): string
    {
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
}
