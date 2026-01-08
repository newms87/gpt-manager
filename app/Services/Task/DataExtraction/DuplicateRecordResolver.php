<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Service for resolving duplicate TeamObjects during data extraction.
 *
 * Before creating a new TeamObject, this service checks if a similar record already exists.
 * It uses LLM-based comparison to handle fuzzy matching (e.g., "John Smith" vs "John W. Smith").
 *
 * Usage Example:
 * ```php
 * $resolver = app(DuplicateRecordResolver::class);
 *
 * // Find potential duplicate candidates
 * $candidates = $resolver->findCandidates(
 *     objectType: 'Demand',
 *     extractedData: ['client_name' => 'John Smith', 'accident_date' => '2024-01-15'],
 *     parentObjectId: null,
 *     schemaDefinitionId: 123
 * );
 *
 * if ($candidates->isNotEmpty()) {
 *     // Try quick exact match first
 *     if ($quickMatch = $resolver->quickMatchCheck($extractedData, $candidates)) {
 *         // Use existing object
 *         return $quickMatch;
 *     }
 *
 *     // Use LLM for fuzzy comparison
 *     $result = $resolver->resolveDuplicate(
 *         extractedData: $extractedData,
 *         candidates: $candidates,
 *         taskRun: $taskRun,
 *         taskProcess: $taskProcess
 *     );
 *
 *     if ($result->hasDuplicate()) {
 *         // Use existing object
 *         return $result->getExistingObject();
 *     }
 * }
 *
 * // Create new object
 * ```
 */
class DuplicateRecordResolver
{
    use HasDebugLogging;

    /**
     * Find potential duplicate TeamObjects within the specified scope.
     *
     * @param  string  $objectType  Type of object to search for
     * @param  array  $extractedData  Extracted identifying data to match against
     * @param  int|null  $parentObjectId  Optional parent object scope
     * @param  int|null  $schemaDefinitionId  Optional schema scope
     * @return Collection<TeamObject> Collection of candidate objects
     */
    public function findCandidates(
        string $objectType,
        array $extractedData,
        ?int $parentObjectId = null,
        ?int $schemaDefinitionId = null
    ): Collection {
        $currentTeam = team();

        if (!$currentTeam) {
            static::logDebug('No team context available for finding duplicate candidates');

            return collect();
        }

        static::logDebug('Finding duplicate candidates', [
            'object_type'          => $objectType,
            'parent_object_id'     => $parentObjectId,
            'schema_definition_id' => $schemaDefinitionId,
            'extracted_fields'     => array_keys($extractedData),
        ]);

        // Query for candidates within scope
        $candidates = TeamObject::query()
            ->where('team_id', $currentTeam->id)
            ->where('type', $objectType)
            ->when($schemaDefinitionId, fn($q) => $q->where('schema_definition_id', $schemaDefinitionId))
            ->when($parentObjectId, fn($q) => $q->where('root_object_id', $parentObjectId))
            ->orderBy('created_at', 'desc')
            ->limit(50) // Reasonable limit for comparison
            ->with(['attributes', 'schemaDefinition']) // Eager load for comparison
            ->get();

        static::logDebug('Found duplicate candidates', [
            'candidate_count' => $candidates->count(),
        ]);

        return $candidates;
    }

    /**
     * Resolve duplicates using LLM comparison.
     *
     * Presents extracted data and candidate objects to the LLM to determine if
     * the extracted data matches any existing record.
     *
     * @param  array  $extractedData  Extracted identifying data
     * @param  Collection  $candidates  Collection of candidate TeamObjects
     * @param  TaskRun  $taskRun  Task run context
     * @param  TaskProcess  $taskProcess  Task process context
     * @return ResolutionResult Resolution outcome with explanation
     *
     * @throws ValidationError if resolution cannot be performed
     */
    public function resolveDuplicate(
        array $extractedData,
        Collection $candidates,
        TaskRun $taskRun,
        TaskProcess $taskProcess
    ): ResolutionResult {
        static::logDebug('Resolving duplicate using LLM', [
            'candidate_count'  => $candidates->count(),
            'extracted_fields' => array_keys($extractedData),
        ]);

        if ($candidates->isEmpty()) {
            static::logDebug('No candidates to compare');

            return new ResolutionResult(
                isDuplicate: false,
                existingObjectId: null,
                existingObject: null,
                explanation: 'No existing records to compare against',
                confidence: 1.0
            );
        }

        // Build comparison prompt for LLM
        $prompt = $this->buildComparisonPrompt($extractedData, $candidates);

        // Create agent thread for comparison
        $thread = $this->buildComparisonThread($taskRun, $taskProcess, $prompt);

        // Run comparison via LLM
        $threadRun = $this->runComparisonThread($thread, $taskProcess);

        // Parse and return result
        return $this->parseResolutionResult($threadRun, $candidates);
    }

    /**
     * Quick check using exact field matching before LLM.
     *
     * Performs exact comparison of identity fields to avoid unnecessary LLM calls
     * for obvious matches.
     *
     * @param  array  $extractedData  Extracted identifying data
     * @param  Collection  $candidates  Collection of candidate TeamObjects
     * @param  array  $identityFields  Fields to compare (if empty, compare all extracted fields)
     * @return TeamObject|null Matching object if exact match found, null otherwise
     */
    public function quickMatchCheck(array $extractedData, Collection $candidates, array $identityFields = []): ?TeamObject
    {
        static::logDebug('Performing quick exact match check', [
            'extracted_data'   => $extractedData,
            'candidate_count'  => $candidates->count(),
            'identity_fields'  => $identityFields,
        ]);

        foreach ($candidates as $candidate) {
            if ($this->isExactMatch($extractedData, $candidate, $identityFields)) {
                static::logDebug('Quick exact match found', [
                    'object_id' => $candidate->id,
                ]);

                return $candidate;
            }
        }

        static::logDebug('No exact match found');

        return null;
    }

    /**
     * Check if extracted data exactly matches a candidate object.
     * Only compares identity fields if provided.
     *
     * @param  array  $extractedData  Extracted identifying data
     * @param  TeamObject  $candidate  Candidate object to compare against
     * @param  array  $identityFields  Fields to compare (if empty, compare all extracted fields)
     */
    protected function isExactMatch(array $extractedData, TeamObject $candidate, array $identityFields = []): bool
    {
        // Build candidate data from object properties and attributes
        $candidateData = [];

        if ($candidate->name) {
            $candidateData['name'] = $candidate->name;
        }

        if ($candidate->date) {
            $candidateData['date'] = $candidate->date->format('Y-m-d');
        }

        foreach ($candidate->attributes as $attribute) {
            $candidateData[$attribute->name] = $attribute->getValue();
        }

        static::logDebug("Comparing against candidate ID {$candidate->id}", [
            'candidate_data' => $candidateData,
        ]);

        // Determine which fields to compare
        $fieldsToCompare = !empty($identityFields) ? $identityFields : array_keys($extractedData);

        static::logDebug('Fields to compare', [
            'identity_fields'   => $identityFields,
            'fields_to_compare' => $fieldsToCompare,
        ]);

        // Compare only the specified fields
        foreach ($fieldsToCompare as $fieldName) {
            // Skip if this field wasn't extracted
            if (!isset($extractedData[$fieldName])) {
                continue;
            }

            $fieldValue = $extractedData[$fieldName];

            if (!isset($candidateData[$fieldName])) {
                // If extracted value is also empty, treat as match (both are effectively empty)
                $extractedNormalized = $this->normalizeValue($fieldValue);
                if ($extractedNormalized === '') {
                    static::logDebug("Field '{$fieldName}' empty in both (extracted empty, candidate missing)", [
                        'candidate_id' => $candidate->id,
                    ]);

                    continue; // This field matches - both are effectively empty
                }

                // Extracted has value but candidate is missing - not a match
                static::logDebug("Field '{$fieldName}' missing in candidate", [
                    'candidate_id'     => $candidate->id,
                    'extracted_value'  => $fieldValue,
                    'available_fields' => array_keys($candidateData),
                ]);

                return false;
            }

            // Normalize for comparison
            $extractedNormalized = $this->normalizeValue($fieldValue);
            $candidateNormalized = $this->normalizeValue($candidateData[$fieldName]);

            if ($extractedNormalized !== $candidateNormalized) {
                static::logDebug("Field '{$fieldName}' mismatch", [
                    'candidate_id'         => $candidate->id,
                    'extracted_value'      => $fieldValue,
                    'candidate_value'      => $candidateData[$fieldName],
                    'extracted_normalized' => $extractedNormalized,
                    'candidate_normalized' => $candidateNormalized,
                ]);

                return false;
            }

            static::logDebug("Field '{$fieldName}' matches", [
                'candidate_id' => $candidate->id,
                'value'        => $fieldValue,
            ]);
        }

        return true;
    }

    /**
     * Normalize a value for comparison.
     */
    protected function normalizeValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        // Convert to string and normalize whitespace/case
        return trim(strtolower((string)$value));
    }

    /**
     * Build comparison prompt for the LLM.
     */
    protected function buildComparisonPrompt(array $extractedData, Collection $candidates): string
    {
        $prompt = "# Duplicate Record Detection\n\n";
        $prompt .= "You are comparing extracted data against existing records to find duplicates.\n\n";

        $prompt .= "## Extracted Data\n\n";
        $prompt .= "```json\n";
        $prompt .= json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $prompt .= "\n```\n\n";

        $prompt .= "## Existing Records\n\n";

        foreach ($candidates as $index => $candidate) {
            $candidateNumber = $index + 1;
            $prompt .= "{$candidateNumber}. **ID: {$candidate->id}**\n\n";

            // Include object properties
            $candidateData = [];

            if ($candidate->name) {
                $candidateData['name'] = $candidate->name;
            }

            if ($candidate->date) {
                $candidateData['date'] = $candidate->date->format('Y-m-d');
            }

            // Include attributes
            foreach ($candidate->attributes as $attribute) {
                $candidateData[$attribute->name] = $attribute->getValue();
            }

            $prompt .= "```json\n";
            $prompt .= json_encode($candidateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $prompt .= "\n```\n\n";
        }

        $prompt .= "## Task\n\n";
        $prompt .= "Determine if the extracted data matches any existing record. Consider:\n\n";
        $prompt .= "- **Name variations:** John Smith vs John W. Smith vs J. Smith\n";
        $prompt .= "- **Date format differences:** 2024-01-15 vs Jan 15, 2024\n";
        $prompt .= "- **Minor spelling variations:** Centre vs Center\n";
        $prompt .= "- **Missing fields:** Some fields may be missing but core identifying fields should match\n";
        $prompt .= "- **Case sensitivity:** Ignore case differences\n\n";

        $prompt .= "**Important:**\n";
        $prompt .= "- Set `is_duplicate` to `true` only if you are confident there is a match\n";
        $prompt .= "- Set `matching_record_id` to the ID of the matching record, or `null` if no match\n";
        $prompt .= "- Set `confidence` to a value between 0.0 and 1.0 (0.0 = no confidence, 1.0 = certain)\n";
        $prompt .= "- Provide a clear explanation citing specific fields that match or differ\n";

        return $prompt;
    }

    /**
     * Build an agent thread for duplicate comparison.
     */
    protected function buildComparisonThread(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        string $prompt
    ): \App\Models\Agent\AgentThread {
        $taskDefinition = $taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new ValidationError('Agent not found for TaskRun: ' . $taskRun->id);
        }

        static::logDebug('Building comparison agent thread', [
            'agent_id' => $taskDefinition->agent->id,
        ]);

        // Create JSON schema for duplicate resolution response
        $schemaDefinition = $this->createDuplicateResolutionSchema();

        // Build thread with system prompt and response schema
        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Duplicate Record Resolution')
            ->withSystemMessage($prompt)
            ->withResponseSchema($schemaDefinition)
            ->build();

        // Associate thread with task process
        $taskProcess->agentThread()->associate($thread)->save();

        return $thread;
    }

    /**
     * Create a transient SchemaDefinition for duplicate resolution response format.
     */
    protected function createDuplicateResolutionSchema(): SchemaDefinition
    {
        $schema = [
            'type'       => 'object',
            'properties' => [
                'is_duplicate'       => [
                    'type'        => 'boolean',
                    'description' => 'Whether the extracted data matches an existing record',
                ],
                'matching_record_id' => [
                    'type'        => ['integer', 'null'],
                    'description' => 'ID of the matching record, or null if no match',
                ],
                'confidence'         => [
                    'type'        => 'number',
                    'minimum'     => 0,
                    'maximum'     => 1,
                    'description' => 'Confidence level between 0.0 (no confidence) and 1.0 (certain)',
                ],
                'explanation'        => [
                    'type'        => 'string',
                    'description' => 'Clear explanation citing specific fields that match or differ',
                ],
            ],
            'required'   => ['is_duplicate', 'matching_record_id', 'confidence', 'explanation'],
        ];

        $schemaDefinition         = new SchemaDefinition;
        $schemaDefinition->schema = $schema;
        $schemaDefinition->name   = 'DuplicateResolutionResponse';
        $schemaDefinition->type   = SchemaDefinition::TYPE_AGENT_RESPONSE;

        return $schemaDefinition;
    }

    /**
     * Run the comparison agent thread and return the thread run.
     */
    protected function runComparisonThread(\App\Models\Agent\AgentThread $thread, TaskProcess $taskProcess): \App\Models\Agent\AgentThreadRun
    {
        static::logDebug('Running comparison agent thread', [
            'thread_id' => $thread->id,
        ]);

        // Get timeout from config
        $timeout = $taskProcess->taskRun->taskDefinition->task_runner_config['duplicate_resolution_timeout'] ?? 60;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Create schema definition for response format
        $schemaDefinition = $this->createDuplicateResolutionSchema();

        // Run the thread with response schema
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($schemaDefinition)
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            throw new ValidationError(
                'Duplicate resolution thread failed: ' . ($threadRun->error ?? 'Unknown error')
            );
        }

        if (!$threadRun->lastMessage) {
            throw new ValidationError('No response received from duplicate resolution agent');
        }

        static::logDebug('Duplicate resolution response received', [
            'message_id' => $threadRun->lastMessage->id,
        ]);

        return $threadRun;
    }

    /**
     * Parse resolution result from LLM response using getJsonContent() method.
     */
    protected function parseResolutionResult(\App\Models\Agent\AgentThreadRun $threadRun, Collection $candidates): ResolutionResult
    {
        static::logDebug('Parsing duplicate resolution result');

        // Use getJsonContent() to properly parse the response
        $data = $threadRun->lastMessage?->getJsonContent();

        if (!$data || !is_array($data)) {
            static::logDebug('Failed to get JSON content from response');

            return new ResolutionResult(
                isDuplicate: false,
                existingObjectId: null,
                existingObject: null,
                explanation: 'Failed to parse LLM response',
                confidence: 0.0
            );
        }

        // Extract fields
        $isDuplicate      = $data['is_duplicate']       ?? false;
        $matchingRecordId = $data['matching_record_id'] ?? null;
        $confidence       = (float)($data['confidence'] ?? 0.0);
        $explanation      = $data['explanation'] ?? 'No explanation provided';

        // Clamp confidence to valid range
        $confidence = max(0.0, min(1.0, $confidence));

        // Find the matching object if specified
        $existingObject = null;
        if ($isDuplicate && $matchingRecordId) {
            $existingObject = $candidates->firstWhere('id', $matchingRecordId);

            if (!$existingObject) {
                static::logDebug('LLM specified non-existent record ID', [
                    'specified_id'   => $matchingRecordId,
                    'candidate_ids'  => $candidates->pluck('id')->toArray(),
                ]);

                // Invalid ID - treat as no match
                return new ResolutionResult(
                    isDuplicate: false,
                    existingObjectId: null,
                    existingObject: null,
                    explanation: "LLM specified invalid record ID: {$matchingRecordId}",
                    confidence: 0.0
                );
            }
        }

        static::logDebug('Parsed resolution result', [
            'is_duplicate'   => $isDuplicate,
            'record_id'      => $matchingRecordId,
            'confidence'     => $confidence,
        ]);

        return new ResolutionResult(
            isDuplicate: $isDuplicate,
            existingObjectId: $matchingRecordId,
            existingObject: $existingObject,
            explanation: $explanation,
            confidence: $confidence
        );
    }
}
