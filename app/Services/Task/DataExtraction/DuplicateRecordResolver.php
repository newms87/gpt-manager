<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use App\Traits\SchemaFieldHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Service for resolving duplicate TeamObjects during data extraction.
 *
 * Before creating a new TeamObject, this service checks if a similar record already exists.
 * It uses exact matching first, then LLM-based comparison for fuzzy matching
 * (e.g., "John Smith" vs "John W. Smith").
 *
 * Usage Example:
 * ```php
 * $resolver = app(DuplicateRecordResolver::class);
 *
 * // Find potential duplicate candidates (checks exact match first)
 * $result = $resolver->findCandidates(
 *     objectType: 'Demand',
 *     searchQueries: [
 *         ['client_name' => ['John', 'Smith']],  // Keyword array format
 *         ['client_name' => ['Smith']],           // Broader keyword search
 *     ],
 *     rootObjectId: null,  // Level 0 root object ID (e.g., Demand)
 *     schemaDefinitionId: 123,
 *     extractedData: ['client_name' => 'John Smith'],
 *     identityFields: ['client_name']
 * );
 *
 * // If exact match found, use it immediately
 * if ($result->hasExactMatch()) {
 *     return TeamObject::find($result->exactMatchId);
 * }
 *
 * // If candidates found but no exact match, use LLM for fuzzy comparison
 * if ($result->candidates->isNotEmpty()) {
 *     $resolution = $resolver->resolveDuplicate(
 *         extractedData: $extractedData,
 *         candidates: $result->candidates,
 *         taskRun: $taskRun,
 *         taskProcess: $taskProcess
 *     );
 *
 *     if ($resolution->hasDuplicate()) {
 *         return $resolution->existingObject;
 *     }
 * }
 *
 * // Create new object
 * ```
 */
class DuplicateRecordResolver
{
    use HasDebugLogging;
    use SchemaFieldHelper;

    // Field type constants for native TeamObject columns
    protected const string TYPE_NATIVE_NAME = 'native_name',
        TYPE_NATIVE_DATE                    = 'native_date';

    // Field type constants for schema-defined attribute types
    protected const string TYPE_DATE = 'date',
        TYPE_DATETIME                = 'date-time',
        TYPE_BOOLEAN                 = 'boolean',
        TYPE_NUMBER                  = 'number',
        TYPE_INTEGER                 = 'integer',
        TYPE_STRING                  = 'string';

    // Native columns on TeamObject that bypass attribute lookup
    protected const array NATIVE_COLUMNS = [
        'name' => self::TYPE_NATIVE_NAME,
        'date' => self::TYPE_NATIVE_DATE,
    ];

    /**
     * Find potential duplicate TeamObjects within the specified scope using search queries.
     *
     * Resolution order (stops at first match):
     * 1. Exact name match (case-insensitive) - prevents exact duplicates
     * 2. Search queries from SPECIFIC to BROAD - checks for exact match after each query
     * 3. Returns smallest non-zero result set for LLM comparison
     *
     * @param  string  $objectType  Type of object to search for
     * @param  array  $searchQueries  Array of search query objects (ordered specific to broad)
     * @param  int|null  $rootObjectId  Optional root object scope (level 0 ancestor, e.g., Demand)
     * @param  int|null  $schemaDefinitionId  Optional schema scope
     * @param  array  $extractedData  Extracted data for exact match comparison
     * @param  array  $identityFields  Fields to compare for exact match
     * @return FindCandidatesResult Result containing candidates and optional exactMatchId
     */
    public function findCandidates(
        string $objectType,
        array $searchQueries,
        ?int $rootObjectId = null,
        ?int $schemaDefinitionId = null,
        array $extractedData = [],
        array $identityFields = []
    ): FindCandidatesResult {
        $currentTeam = team();

        if (!$currentTeam) {
            static::logDebug('No team context available for finding duplicate candidates');

            return new FindCandidatesResult(collect());
        }

        static::logDebug('Finding duplicate candidates with search queries', [
            'object_type'          => $objectType,
            'root_object_id'       => $rootObjectId,
            'schema_definition_id' => $schemaDefinitionId,
            'search_query_count'   => count($searchQueries),
        ]);

        // Load schema once for field type detection
        $schema = null;
        if ($schemaDefinitionId) {
            $schema = SchemaDefinition::find($schemaDefinitionId)?->schema;
        }

        // Step 1: Try exact name match first (prevents exact duplicates)
        if (!empty($extractedData['name'])) {
            $nameMatches = $this->findAllByExactName(
                $objectType,
                $extractedData['name'],
                $rootObjectId,
                $schemaDefinitionId
            );

            // Check each name match for full identity field match
            foreach ($nameMatches as $candidate) {
                if ($this->isExactMatch($extractedData, $candidate, $identityFields, $schema)) {
                    static::logDebug('Exact match found among name matches', ['object_id' => $candidate->id]);

                    return new FindCandidatesResult(
                        candidates: collect([$candidate]),
                        exactMatchId: $candidate->id
                    );
                }
            }

            // No exact match found among name matches - continue to search queries
            // (name matches without identity field match are not considered duplicates)
            if ($nameMatches->isNotEmpty()) {
                static::logDebug('Name matches found but no identity field match', [
                    'name_match_count' => $nameMatches->count(),
                    'identity_fields'  => $identityFields,
                ]);
            }
        }

        // Step 2: If no search queries provided, fall back to basic scope-only query
        if (empty($searchQueries)) {
            return new FindCandidatesResult(
                $this->executeSearchQuery($objectType, [], $rootObjectId, $schemaDefinitionId)
            );
        }

        $allResultSets = [];

        // Run queries from specific to broad, checking for exact match after each
        foreach ($searchQueries as $index => $query) {
            if (!is_array($query)) {
                continue;
            }

            $results = $this->executeSearchQuery($objectType, $query, $rootObjectId, $schemaDefinitionId);

            static::logDebug("Search query #{$index} results", [
                'query'        => $query,
                'result_count' => $results->count(),
            ]);

            if ($results->isEmpty()) {
                continue;
            }

            // Check for exact match in these results (if extracted data provided)
            if (!empty($extractedData)) {
                foreach ($results as $candidate) {
                    if ($this->isExactMatch($extractedData, $candidate, $identityFields, $schema)) {
                        static::logDebug('Exact match found, stopping search', [
                            'query_index' => $index,
                            'object_id'   => $candidate->id,
                        ]);

                        return new FindCandidatesResult(
                            collect([$candidate]),
                            $candidate->id
                        );
                    }
                }
            }

            // Track this result set for potential LLM comparison
            $allResultSets[] = [
                'query_index' => $index,
                'count'       => $results->count(),
                'results'     => $results,
            ];
        }

        // No exact match found - return smallest non-zero result set for LLM comparison
        if (empty($allResultSets)) {
            static::logDebug('No candidates found from any query');

            return new FindCandidatesResult(collect());
        }

        // Sort by count ascending to get smallest set
        usort($allResultSets, fn($a, $b) => $a['count'] <=> $b['count']);

        $smallest = $allResultSets[0];
        static::logDebug('No exact match, using smallest result set for LLM comparison', [
            'query_index'  => $smallest['query_index'],
            'result_count' => $smallest['count'],
        ]);

        return new FindCandidatesResult($smallest['results']);
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
     * @param  int|null  $rootObjectId  Optional root object scope (level 0 ancestor, e.g., Demand)
     * @param  int|null  $schemaDefinitionId  Optional schema scope
     * @return Collection<TeamObject> Collection of matching objects
     */
    protected function executeSearchQuery(
        string $objectType,
        array $query,
        ?int $rootObjectId,
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
            ->when($rootObjectId, fn($q) => $q->where('root_object_id', $rootObjectId));

        // Apply type-aware filters for non-null query fields
        foreach ($query as $field => $criteria) {
            // Skip null, empty strings, or empty arrays
            if ($criteria === null || $criteria === '' || $criteria === []) {
                continue;
            }

            $fieldType = $this->getFieldType($field, $schema);
            $this->applyFieldFilter($baseQuery, $field, $criteria, $fieldType);
        }

        return $baseQuery->orderBy('created_at', 'desc')
            ->limit(50)
            ->with(['attributes'])
            ->get();
    }

    /**
     * Find all TeamObjects by exact name match (case-insensitive).
     *
     * This is checked before running search queries to immediately catch exact duplicates,
     * which prevents creating objects with identical names.
     *
     * Returns a Collection because multiple records may exist with the same name
     * but different identity field values (e.g., same name but different dates).
     *
     * @param  string  $objectType  Type of object to search for
     * @param  string  $name  Exact name to match (case-insensitive)
     * @param  int|null  $rootObjectId  Optional root object scope (level 0 ancestor, e.g., Demand)
     * @param  int|null  $schemaDefinitionId  Optional schema scope
     * @return Collection<TeamObject> Collection of matching objects
     */
    protected function findAllByExactName(
        string $objectType,
        string $name,
        ?int $rootObjectId,
        ?int $schemaDefinitionId
    ): Collection {
        return TeamObject::query()
            ->where('team_id', team()->id)
            ->where('type', $objectType)
            ->whereRaw('LOWER(name) = LOWER(?)', [$name])
            ->when($schemaDefinitionId, fn($q) => $q->where('schema_definition_id', $schemaDefinitionId))
            ->when($rootObjectId, fn($q) => $q->where('root_object_id', $rootObjectId))
            ->with(['attributes'])
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
        // Check native columns first - these have special query handling
        if (isset(self::NATIVE_COLUMNS[$fieldName])) {
            return self::NATIVE_COLUMNS[$fieldName];
        }

        // Use trait method for schema-based field type detection
        $schemaType = $this->getSchemaFieldType($fieldName, $schema);

        // Map schema types to internal type constants
        return match ($schemaType) {
            'date'      => self::TYPE_DATE,
            'date-time' => self::TYPE_DATETIME,
            'boolean'   => self::TYPE_BOOLEAN,
            'number'    => self::TYPE_NUMBER,
            'integer'   => self::TYPE_INTEGER,
            default     => self::TYPE_STRING,
        };
    }

    /**
     * Apply the appropriate filter for a field based on its resolved type.
     *
     * Handles both string patterns (LIKE) and structured criteria (operator-based).
     *
     * @param  mixed  $criteria  Either a string (LIKE pattern) or array (structured criteria)
     */
    protected function applyFieldFilter($query, string $field, mixed $criteria, string $type): void
    {
        match ($type) {
            self::TYPE_NATIVE_NAME => $this->applyNativeNameFilter($query, $criteria),
            self::TYPE_NATIVE_DATE => $this->applyNativeDateFilter($query, $criteria),
            self::TYPE_DATE, self::TYPE_DATETIME => $this->applyDateAttributeFilter($query, $field, $criteria),
            self::TYPE_BOOLEAN => $this->applyBooleanAttributeFilter($query, $field, $criteria),
            self::TYPE_NUMBER, self::TYPE_INTEGER => $this->applyNumericAttributeFilter($query, $field, $criteria),
            default => $this->applyStringAttributeFilter($query, $field, $criteria),
        };
    }

    /**
     * Apply filter for native 'name' column.
     * Supports both string LIKE patterns and keyword arrays.
     *
     * Keyword array format: ['keyword1', 'keyword2', ...]
     * All keywords must be present (AND logic), but order doesn't matter.
     */
    protected function applyNativeNameFilter($query, mixed $criteria): void
    {
        // Keyword array: all keywords must be present (AND logic)
        if (is_array($criteria) && !empty($criteria) && isset($criteria[0]) && is_string($criteria[0])) {
            $query->where(function ($q) use ($criteria) {
                foreach ($criteria as $keyword) {
                    if (is_string($keyword) && $keyword !== '') {
                        $q->whereRaw('name ILIKE ?', ['%' . $keyword . '%']);
                    }
                }
            });

            return;
        }

        // String LIKE pattern (alternative to keyword array)
        if (is_string($criteria)) {
            $query->whereRaw('name ILIKE ?', [$criteria]);
        }
    }

    /**
     * Apply filter for native 'date' column.
     * Supports both string patterns and structured criteria.
     */
    protected function applyNativeDateFilter($query, mixed $criteria): void
    {
        if (is_array($criteria)) {
            $this->applyNativeDateOperatorFilter($query, $criteria);
        } else {
            // String pattern for LIKE matching
            $normalizedPattern = $this->normalizeDateSearchPattern($criteria) ?? $criteria;
            $query->whereRaw("TO_CHAR(date, 'YYYY-MM-DD') ILIKE ?", [$normalizedPattern]);
        }
    }

    /**
     * Apply operator-based filter for native 'date' column.
     */
    protected function applyNativeDateOperatorFilter($query, array $criteria): void
    {
        $operator = $criteria['operator'] ?? '=';
        $value    = $criteria['value']    ?? null;
        $value2   = $criteria['value2']   ?? null;

        if ($value === null) {
            return;
        }

        // Normalize the date value to ISO format
        $normalizedValue = $this->normalizeDateValue($value);

        if ($operator === 'between' && $value2 !== null) {
            $normalizedValue2 = $this->normalizeDateValue($value2);
            $query->whereBetween('date', [$normalizedValue, $normalizedValue2]);
        } else {
            $query->whereRaw("TO_CHAR(date, 'YYYY-MM-DD') {$operator} ?", [$normalizedValue]);
        }
    }

    /**
     * Apply filter for date attributes.
     * Supports both string patterns and structured criteria.
     */
    protected function applyDateAttributeFilter($query, string $field, mixed $criteria): void
    {
        if (is_array($criteria)) {
            $this->applyDateAttributeOperatorFilter($query, $field, $criteria);
        } else {
            // String pattern for LIKE matching
            $normalizedPattern = $this->normalizeDateSearchPattern($criteria);
            if (!$normalizedPattern) {
                return;
            }

            $query->whereHas('attributes', function ($q) use ($field, $normalizedPattern) {
                $q->where('name', $field)
                    ->where(function ($sub) use ($normalizedPattern) {
                        $sub->whereRaw('text_value ILIKE ?', [$normalizedPattern])
                            ->orWhereRaw('json_value::text ILIKE ?', [$normalizedPattern]);
                    });
            });
        }
    }

    /**
     * Apply operator-based filter for date attributes.
     */
    protected function applyDateAttributeOperatorFilter($query, string $field, array $criteria): void
    {
        $operator = $criteria['operator'] ?? '=';
        $value    = $criteria['value']    ?? null;
        $value2   = $criteria['value2']   ?? null;

        if ($value === null) {
            return;
        }

        // Normalize the date value to ISO format
        $normalizedValue = $this->normalizeDateValue($value);

        $query->whereHas('attributes', function ($q) use ($field, $operator, $normalizedValue, $value2) {
            $q->where('name', $field);

            if ($operator === 'between' && $value2 !== null) {
                $normalizedValue2 = $this->normalizeDateValue($value2);
                $q->where(function ($sub) use ($normalizedValue, $normalizedValue2) {
                    $sub->whereBetween('text_value', [$normalizedValue, $normalizedValue2])
                        ->orWhereRaw('json_value::text >= ? AND json_value::text <= ?', [$normalizedValue, $normalizedValue2]);
                });
            } else {
                $q->where(function ($sub) use ($operator, $normalizedValue) {
                    $sub->where('text_value', $operator, $normalizedValue)
                        ->orWhereRaw("json_value::text {$operator} ?", [$normalizedValue]);
                });
            }
        });
    }

    /**
     * Apply filter for boolean attributes.
     * Supports both string patterns and direct boolean values.
     */
    protected function applyBooleanAttributeFilter($query, string $field, mixed $criteria): void
    {
        // Handle direct boolean values
        if (is_bool($criteria)) {
            $boolValue = $criteria;
        } else {
            // String pattern - normalize truthy/falsy strings to boolean
            $cleanPattern = strtolower(trim((string)$criteria, '%'));
            $boolValue    = in_array($cleanPattern, ['true', '1', 'yes'], true);
        }

        $query->whereHas('attributes', function ($q) use ($field, $boolValue) {
            $q->where('name', $field)
                ->where(function ($sub) use ($boolValue) {
                    $textValue = $boolValue ? 'true' : 'false';
                    $sub->where('text_value', $textValue)
                        ->orWhereRaw('json_value::text = ?', [$boolValue ? 'true' : 'false']);
                });
        });
    }

    /**
     * Apply filter for numeric attributes.
     * Supports both string patterns and structured criteria.
     */
    protected function applyNumericAttributeFilter($query, string $field, mixed $criteria): void
    {
        if (is_array($criteria)) {
            $this->applyNumericAttributeOperatorFilter($query, $field, $criteria);
        } else {
            // String pattern for LIKE matching
            $query->whereHas('attributes', function ($q) use ($field, $criteria) {
                $q->where('name', $field)
                    ->where(function ($sub) use ($criteria) {
                        $sub->whereRaw('text_value ILIKE ?', [$criteria])
                            ->orWhereRaw('json_value::text ILIKE ?', [$criteria]);
                    });
            });
        }
    }

    /**
     * Apply operator-based filter for numeric attributes.
     */
    protected function applyNumericAttributeOperatorFilter($query, string $field, array $criteria): void
    {
        $operator = $criteria['operator'] ?? '=';
        $value    = $criteria['value']    ?? null;
        $value2   = $criteria['value2']   ?? null;

        if ($value === null) {
            return;
        }

        $query->whereHas('attributes', function ($q) use ($field, $operator, $value, $value2) {
            $q->where('name', $field);

            if ($operator === 'between' && $value2 !== null) {
                $q->where(function ($sub) use ($value, $value2) {
                    // Cast text_value to numeric for proper comparison
                    $sub->whereRaw('CAST(text_value AS DECIMAL) >= ? AND CAST(text_value AS DECIMAL) <= ?', [$value, $value2])
                        ->orWhereRaw('CAST(json_value::text AS DECIMAL) >= ? AND CAST(json_value::text AS DECIMAL) <= ?', [$value, $value2]);
                });
            } else {
                $q->where(function ($sub) use ($operator, $value) {
                    // Cast text_value to numeric for proper comparison
                    $sub->whereRaw("CAST(text_value AS DECIMAL) {$operator} ?", [$value])
                        ->orWhereRaw("CAST(json_value::text AS DECIMAL) {$operator} ?", [$value]);
                });
            }
        });
    }

    /**
     * Apply filter for string attributes.
     * Supports both string LIKE patterns and keyword arrays.
     *
     * Keyword array format: ['keyword1', 'keyword2', ...]
     * All keywords must be present (AND logic), but order doesn't matter.
     */
    protected function applyStringAttributeFilter($query, string $field, mixed $criteria): void
    {
        // Keyword array: all keywords must be present (AND logic)
        if (is_array($criteria) && !empty($criteria) && isset($criteria[0]) && is_string($criteria[0])) {
            $query->whereHas('attributes', function ($q) use ($field, $criteria) {
                $q->where('name', $field)
                    ->where(function ($sub) use ($criteria) {
                        foreach ($criteria as $keyword) {
                            if (is_string($keyword) && $keyword !== '') {
                                $sub->where(function ($inner) use ($keyword) {
                                    $inner->whereRaw('text_value ILIKE ?', ['%' . $keyword . '%'])
                                        ->orWhereRaw('json_value::text ILIKE ?', ['%' . $keyword . '%']);
                                });
                            }
                        }
                    });
            });

            return;
        }

        // String LIKE pattern (alternative to keyword array)
        if (is_string($criteria)) {
            $query->whereHas('attributes', function ($q) use ($field, $criteria) {
                $q->where('name', $field)
                    ->where(function ($sub) use ($criteria) {
                        $sub->whereRaw('text_value ILIKE ?', [$criteria])
                            ->orWhereRaw('json_value::text ILIKE ?', [$criteria]);
                    });
            });
        }
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
     * Check if extracted data exactly matches a candidate object.
     * Only compares identity fields if provided.
     *
     * Date fields are normalized to ISO format (Y-m-d) before comparison to handle
     * different date format representations (e.g., "12/04/2024" vs "2024-12-04").
     *
     * @param  array  $extractedData  Extracted identifying data
     * @param  TeamObject  $candidate  Candidate object to compare against
     * @param  array  $identityFields  Fields to compare (if empty, compare all extracted fields)
     * @param  array|null  $schema  Schema for field type detection (pre-loaded for efficiency)
     */
    protected function isExactMatch(array $extractedData, TeamObject $candidate, array $identityFields = [], ?array $schema = null): bool
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

            // Check if this is a date field and normalize accordingly
            if ($this->isDateFieldForExactMatch($fieldName, $schema)) {
                $extractedNormalized = $this->normalizeDateForComparison($fieldValue);
                $candidateNormalized = $this->normalizeDateForComparison($candidateData[$fieldName]);
            } else {
                // Standard string normalization for non-date fields
                $extractedNormalized = $this->normalizeValue($fieldValue);
                $candidateNormalized = $this->normalizeValue($candidateData[$fieldName]);
            }

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
     * Check if a field is a date field for exact match comparison.
     *
     * Handles both native 'date' column and schema-defined date fields.
     */
    protected function isDateFieldForExactMatch(string $fieldName, ?array $schema): bool
    {
        // Native 'date' column on TeamObject
        if ($fieldName === 'date') {
            return true;
        }

        // Check schema for date/date-time format using trait method
        return $this->isDateField($fieldName, $schema);
    }

    /**
     * Normalize a date value to ISO format (Y-m-d) for exact match comparison.
     *
     * Uses Carbon to parse various date formats and outputs a normalized ISO date.
     * Falls back to standard string normalization if parsing fails.
     */
    protected function normalizeDateForComparison(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Exception) {
            // Fall back to standard string normalization if date parsing fails
            return $this->normalizeValue($value);
        }
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
    ): AgentThread {
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
    protected function runComparisonThread(AgentThread $thread, TaskProcess $taskProcess): AgentThreadRun
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
    protected function parseResolutionResult(AgentThreadRun $threadRun, Collection $candidates): ResolutionResult
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
