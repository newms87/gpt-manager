<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Carbon\Carbon;
use Exception;
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
 *     searchQueries: [
 *         ['client_name' => '%John Smith%'],
 *         ['client_name' => '%John Smith%', 'accident_date' => '%2024-01-15%'],
 *     ],
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

    // Field type constants
    protected const string TYPE_NATIVE_NAME = 'native_name';

    protected const string TYPE_NATIVE_DATE = 'native_date';

    protected const string TYPE_DATE = 'date';

    protected const string TYPE_DATETIME = 'date-time';

    protected const string TYPE_BOOLEAN = 'boolean';

    protected const string TYPE_NUMBER = 'number';

    protected const string TYPE_INTEGER = 'integer';

    protected const string TYPE_STRING = 'string';

    // Native columns on TeamObject that bypass attribute lookup
    protected const array NATIVE_COLUMNS = [
        'name' => self::TYPE_NATIVE_NAME,
        'date' => self::TYPE_NATIVE_DATE,
    ];

    /**
     * Find potential duplicate TeamObjects within the specified scope using search queries.
     *
     * Iterates through search queries from least to most restrictive:
     * - If a query returns 1-5 results: return those (good match set)
     * - If a query returns >5 results: try the next more restrictive query
     * - If a more restrictive query returns 0: fall back to previous results (limit 20)
     * - If all queries return >5: return last query results (limit 20)
     *
     * @param  string  $objectType  Type of object to search for
     * @param  array  $searchQueries  Array of search query objects with SQL LIKE patterns
     * @param  int|null  $parentObjectId  Optional parent object scope
     * @param  int|null  $schemaDefinitionId  Optional schema scope
     * @return Collection<TeamObject> Collection of candidate objects
     */
    public function findCandidates(
        string $objectType,
        array $searchQueries,
        ?int $parentObjectId = null,
        ?int $schemaDefinitionId = null
    ): Collection {
        $currentTeam = team();

        if (!$currentTeam) {
            static::logDebug('No team context available for finding duplicate candidates');

            return collect();
        }

        static::logDebug('Finding duplicate candidates with search queries', [
            'object_type'          => $objectType,
            'parent_object_id'     => $parentObjectId,
            'schema_definition_id' => $schemaDefinitionId,
            'search_query_count'   => count($searchQueries),
        ]);

        // If no search queries provided, fall back to basic scope-only query
        if (empty($searchQueries)) {
            return $this->executeSearchQuery($objectType, [], $parentObjectId, $schemaDefinitionId);
        }

        $previousResults = collect();

        foreach ($searchQueries as $index => $query) {
            if (!is_array($query)) {
                continue;
            }

            $results = $this->executeSearchQuery($objectType, $query, $parentObjectId, $schemaDefinitionId);

            static::logDebug("Search query {$index} results", [
                'query'        => $query,
                'result_count' => $results->count(),
            ]);

            // If we get 1-5 results, this is a good match set
            if ($results->count() >= 1 && $results->count() <= 5) {
                static::logDebug('Found optimal candidate set', ['count' => $results->count()]);

                return $results;
            }

            // If this more restrictive query returned 0 results, fall back to previous
            if ($results->count() === 0 && $previousResults->isNotEmpty()) {
                static::logDebug('Query too restrictive, falling back to previous results', [
                    'previous_count' => $previousResults->count(),
                ]);

                return $previousResults->take(20);
            }

            $previousResults = $results;
        }

        // If all queries returned >5, use the last query results (limit 20)
        static::logDebug('All queries returned many results, limiting to 20', [
            'total_found' => $previousResults->count(),
        ]);

        return $previousResults->take(20);
    }

    /**
     * Execute a single search query against TeamObjects.
     *
     * Applies type-aware filters based on field type resolution:
     * - Native columns (name, date): Direct column queries
     * - Schema-typed fields: Type-specific attribute queries
     *
     * @param  string  $objectType  Type of object to search for
     * @param  array  $query  Search query with field => LIKE pattern mappings
     * @param  int|null  $parentObjectId  Optional parent object scope
     * @param  int|null  $schemaDefinitionId  Optional schema scope
     * @return Collection<TeamObject> Collection of matching objects
     */
    protected function executeSearchQuery(
        string $objectType,
        array $query,
        ?int $parentObjectId,
        ?int $schemaDefinitionId
    ): Collection {
        $currentTeam = team();

        // Load schema once for field type detection
        $schema = null;
        if ($schemaDefinitionId) {
            $schemaDefinition = SchemaDefinition::find($schemaDefinitionId);
            $schema           = $schemaDefinition?->schema;
        }

        $baseQuery = TeamObject::query()
            ->where('team_id', $currentTeam->id)
            ->where('type', $objectType)
            ->when($schemaDefinitionId, fn($q) => $q->where('schema_definition_id', $schemaDefinitionId))
            ->when($parentObjectId, fn($q) => $q->where('root_object_id', $parentObjectId));

        // Apply type-aware filters for non-null query fields
        foreach ($query as $field => $pattern) {
            if ($pattern === null || $pattern === '') {
                continue;
            }

            $fieldType = $this->getFieldType($field, $schema);
            $this->applyFieldFilter($baseQuery, $field, $pattern, $fieldType);
        }

        return $baseQuery->orderBy('created_at', 'desc')
            ->limit(50)
            ->with(['attributes', 'schemaDefinition'])
            ->get();
    }

    /**
     * Determine the effective type of a field for query building.
     *
     * Resolution priority:
     * 1. Native TeamObject columns (name, date)
     * 2. Schema format (date, date-time)
     * 3. Schema type (boolean, number, integer, string)
     * 4. Default to string
     */
    protected function getFieldType(string $fieldName, ?array $schema): string
    {
        // 1. Check native columns first
        if (isset(self::NATIVE_COLUMNS[$fieldName])) {
            return self::NATIVE_COLUMNS[$fieldName];
        }

        // 2. Check schema definition
        if (!$schema) {
            return self::TYPE_STRING;
        }

        $properties = $schema['properties']   ?? [];
        $fieldDef   = $properties[$fieldName] ?? null;

        if (!$fieldDef) {
            return self::TYPE_STRING;
        }

        // 3. Check format first (more specific than type)
        $format = $fieldDef['format'] ?? null;
        if ($format === 'date') {
            return self::TYPE_DATE;
        }
        if ($format === 'date-time') {
            return self::TYPE_DATETIME;
        }

        // 4. Check type
        $type = $fieldDef['type'] ?? null;

        return match ($type) {
            'boolean' => self::TYPE_BOOLEAN,
            'number'  => self::TYPE_NUMBER,
            'integer' => self::TYPE_INTEGER,
            default   => self::TYPE_STRING,
        };
    }

    /**
     * Apply the appropriate filter for a field based on its resolved type.
     */
    protected function applyFieldFilter($query, string $field, string $pattern, string $type): void
    {
        match ($type) {
            self::TYPE_NATIVE_NAME => $this->applyNativeNameFilter($query, $pattern),
            self::TYPE_NATIVE_DATE => $this->applyNativeDateFilter($query, $pattern),
            self::TYPE_DATE, self::TYPE_DATETIME => $this->applyDateAttributeFilter($query, $field, $pattern),
            self::TYPE_BOOLEAN => $this->applyBooleanAttributeFilter($query, $field, $pattern),
            self::TYPE_NUMBER, self::TYPE_INTEGER => $this->applyNumericAttributeFilter($query, $field, $pattern),
            default => $this->applyStringAttributeFilter($query, $field, $pattern),
        };
    }

    protected function applyNativeNameFilter($query, string $pattern): void
    {
        $query->where('name', 'LIKE', $pattern);
    }

    protected function applyNativeDateFilter($query, string $pattern): void
    {
        $normalizedPattern = $this->normalizeDateSearchPattern($pattern) ?? $pattern;
        $query->whereRaw("TO_CHAR(date, 'YYYY-MM-DD') LIKE ?", [$normalizedPattern]);
    }

    protected function applyDateAttributeFilter($query, string $field, string $pattern): void
    {
        $normalizedPattern = $this->normalizeDateSearchPattern($pattern);
        if (!$normalizedPattern) {
            return;
        }

        $query->whereHas('attributes', function ($q) use ($field, $normalizedPattern) {
            $q->where('name', $field)
                ->where(function ($sub) use ($normalizedPattern) {
                    $sub->where('text_value', 'LIKE', $normalizedPattern)
                        ->orWhereRaw('json_value::text LIKE ?', [$normalizedPattern]);
                });
        });
    }

    protected function applyBooleanAttributeFilter($query, string $field, string $pattern): void
    {
        $cleanPattern = strtolower(trim($pattern, '%'));
        $boolValue    = in_array($cleanPattern, ['true', '1', 'yes'], true);

        $query->whereHas('attributes', function ($q) use ($field, $boolValue) {
            $q->where('name', $field)
                ->where(function ($sub) use ($boolValue) {
                    $textValue = $boolValue ? 'true' : 'false';
                    $sub->where('text_value', $textValue)
                        ->orWhereRaw('json_value::text = ?', [$boolValue ? 'true' : 'false']);
                });
        });
    }

    protected function applyNumericAttributeFilter($query, string $field, string $pattern): void
    {
        $query->whereHas('attributes', function ($q) use ($field, $pattern) {
            $q->where('name', $field)
                ->where(function ($sub) use ($pattern) {
                    $sub->where('text_value', 'LIKE', $pattern)
                        ->orWhereRaw('json_value::text LIKE ?', [$pattern]);
                });
        });
    }

    protected function applyStringAttributeFilter($query, string $field, string $pattern): void
    {
        $query->whereHas('attributes', function ($q) use ($field, $pattern) {
            $q->where('name', $field)
                ->where(function ($sub) use ($pattern) {
                    $sub->where('text_value', 'LIKE', $pattern)
                        ->orWhereRaw('json_value::text LIKE ?', [$pattern]);
                });
        });
    }

    /**
     * Normalize a date search pattern to ISO format (YYYY-MM-DD) for database comparison.
     *
     * Handles various input formats like:
     * - %10/23/2017% (MM/DD/YYYY)
     * - %2017-10-23% (already ISO)
     * - %Oct 23, 2017%
     *
     * @param  string  $pattern  The LIKE pattern containing a date
     * @return string|null The normalized pattern with ISO date, or null if parsing fails
     */
    protected function normalizeDateSearchPattern(string $pattern): ?string
    {
        // Extract the date portion from the LIKE pattern (remove % wildcards)
        $dateString = trim($pattern, '%');

        if (empty($dateString)) {
            return $pattern; // Keep wildcards-only patterns as-is
        }

        try {
            // Use Carbon::parse to parse various date formats
            $parsedDate = Carbon::parse($dateString);
            $isoDate    = $parsedDate->format('Y-m-d');

            // Reconstruct the LIKE pattern with normalized date
            $prefix = str_starts_with($pattern, '%') ? '%' : '';
            $suffix = str_ends_with($pattern, '%') ? '%' : '';

            return $prefix . $isoDate . $suffix;
        } catch (Exception $e) {
            static::logDebug('Failed to parse date pattern', [
                'pattern' => $pattern,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
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
        $candidateData = $this->buildCandidateData($candidate);

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
     * Build candidate data array from TeamObject properties and attributes.
     */
    protected function buildCandidateData(TeamObject $candidate): array
    {
        $data = [];

        if ($candidate->name) {
            $data['name'] = $candidate->name;
        }

        if ($candidate->date) {
            $data['date'] = $candidate->date->format('Y-m-d');
        }

        foreach ($candidate->attributes as $attribute) {
            $data[$attribute->name] = $attribute->getValue();
        }

        return $data;
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

            $candidateData = $this->buildCandidateData($candidate);

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
