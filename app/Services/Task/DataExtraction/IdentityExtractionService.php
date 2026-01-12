<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Agent\AgentThread;
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
use App\Traits\SchemaFieldHelper;
use App\Traits\TeamObjectRelationshipHelper;
use Exception;
use Illuminate\Support\Collection;

/**
 * Handles the complete identity extraction workflow for a task process.
 *
 * This service orchestrates:
 * 1. Schema building with fragment selector for identity fields
 * 2. LLM thread execution to extract identity data + search query
 * 3. Duplicate resolution using DuplicateRecordResolver
 * 4. TeamObject creation or resolution
 * 5. Artifact building using ExtractionArtifactBuilder
 *
 * Usage Example:
 * ```php
 * $service = app(IdentityExtractionService::class);
 * $teamObject = $service->execute(
 *     taskRun: $taskRun,
 *     taskProcess: $taskProcess,
 *     identityGroup: ['object_type' => 'Demand', 'identity_fields' => ['client_name']],
 *     level: 0
 * );
 * ```
 */
class IdentityExtractionService
{
    use HasDebugLogging;
    use SchemaFieldHelper;
    use TeamObjectRelationshipHelper;

    /**
     * Execute identity extraction for a task process.
     *
     * Returns the created/resolved TeamObject or null if no identity data found.
     */
    public function execute(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        int $level
    ): ?TeamObject {
        $artifacts = $taskProcess->inputArtifacts;

        if ($artifacts->isEmpty()) {
            throw new Exception(
                "Extract Identity process {$taskProcess->id} has no input artifacts. " .
                'This is a bug - processes should not be created without artifacts.'
            );
        }

        // Resolve parent object context
        [$parentObjectId, $possibleParentIds] = $this->resolveParentContext($taskProcess, $identityGroup, $level);

        static::logDebug('Running identity extraction', [
            'level'                  => $level,
            'group'                  => $identityGroup['name'] ?? 'unknown',
            'artifact_count'         => $artifacts->count(),
            'possible_parents_count' => count($possibleParentIds),
        ]);

        // LLM Call #1: Extract identity fields + search query
        $extractionResult = $this->extractIdentityWithSearchQuery(
            $taskRun,
            $taskProcess,
            $artifacts,
            $identityGroup,
            $possibleParentIds
        );

        if (empty($extractionResult)) {
            static::logDebug('No identity data extracted');

            return null;
        }

        // Use LLM-resolved parent_id when available (from multiple parent resolution),
        // otherwise use the pre-resolved parentObjectId from resolved_objects
        $resolvedParentId = $extractionResult['parent_id'] ?? $parentObjectId;

        // Check if this is an array-type extraction (e.g., multiple diagnoses per document)
        $fragmentSelectorService = app(FragmentSelectorService::class);
        $isLeafArrayType         = $fragmentSelectorService->isLeafArrayType($identityGroup);

        static::logDebug('Determining extraction path', [
            'object_type'        => $identityGroup['object_type'] ?? 'unknown',
            'is_leaf_array_type' => $isLeafArrayType,
            'fragment_selector'  => $identityGroup['fragment_selector'] ?? [],
            'extraction_path'    => $isLeafArrayType ? 'array' : 'single',
        ]);

        if ($isLeafArrayType) {
            return $this->executeArrayIdentityExtraction(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                identityGroup: $identityGroup,
                extractionResult: $extractionResult,
                level: $level,
                parentObjectId: $resolvedParentId
            );
        }

        // Single-object extraction path
        return $this->executeSingleIdentityExtraction(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            extractionResult: $extractionResult,
            level: $level,
            parentObjectId: $resolvedParentId
        );
    }

    /**
     * Resolve parent object context from process meta.
     *
     * Returns [resolvedParentId, possibleParentIds] tuple:
     * - resolvedParentId: Single parent ID if determinable, null if multiple or none
     * - possibleParentIds: Array of all possible parent IDs for LLM resolution
     *
     * Parent object IDs are set during process creation by ExtractionProcessOrchestrator.
     *
     * @return array{?int, array<int>}
     */
    protected function resolveParentContext(TaskProcess $taskProcess, array $identityGroup, int $level): array
    {
        // Get parent type from fragment_selector (second-to-last key in path)
        $fragmentSelector = $identityGroup['fragment_selector'] ?? [];
        $parentType       = app(FragmentSelectorService::class)->getParentType($fragmentSelector);

        // Determine if this is a root object (no parent type = fragment_selector path < 2)
        $isRootObject = $parentType === null;

        // Parent object IDs are set during process creation by ExtractionProcessOrchestrator
        $possibleParentIds = $taskProcess->meta['parent_object_ids'] ?? [];

        $parentObjectId = $this->determineParentObjectId(
            $possibleParentIds,
            $isRootObject,
            $parentType,
            $identityGroup['object_type']
        );

        static::logDebug('Parent resolution', [
            'level'                  => $level,
            'parent_type'            => $parentType,
            'is_root'                => $isRootObject,
            'possible_parents_count' => count($possibleParentIds),
            'resolved_parent_id'     => $parentObjectId,
        ]);

        return [$parentObjectId, $possibleParentIds];
    }

    /**
     * Determine the parent object ID from possible candidates.
     *
     * @param  array<int>  $possibleParentIds
     */
    protected function determineParentObjectId(
        array $possibleParentIds,
        bool $isRootObject,
        ?string $parentType,
        string $objectType
    ): ?int {
        if (count($possibleParentIds) === 0) {
            if ($isRootObject) {
                return null;
            }

            throw new Exception(
                "No parent objects of type '{$parentType}' found in resolved_objects for " .
                "{$objectType}. This indicates a missing extraction at a previous level."
            );
        }

        if (count($possibleParentIds) === 1) {
            return $possibleParentIds[0];
        }

        // Multiple parents - let LLM decide during extraction
        return null;
    }

    /**
     * Execute single-object identity extraction (non-array type).
     *
     * Handles unwrapping, name resolution, duplicate checking, and TeamObject creation
     * for identity groups that produce a single object per extraction.
     */
    protected function executeSingleIdentityExtraction(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        array $extractionResult,
        int $level,
        ?int $parentObjectId
    ): ?TeamObject {
        // The data is now simplified to leaf level only: { leaf_key: { fields..., _search_query: {...} } }
        // We need to extract the leaf key's content directly
        $fragmentSelector = $identityGroup['fragment_selector'] ?? [];
        $objectType       = $identityGroup['object_type']       ?? '';
        $leafKey          = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);

        // Get the data for the leaf key (no complex unwrapping needed - schema is already simplified)
        $extractedData      = $extractionResult['data'] ?? [];
        $identificationData = $extractedData[$leafKey]  ?? [];

        // If identification data is not an array, we cannot extract identity fields - return null
        if (!is_array($identificationData)) {
            static::logDebug("Identity extraction found non-array data for {$identityGroup['object_type']} - cannot extract identity fields", [
                'identification_data' => $identificationData,
            ]);

            return null;
        }

        // Extract embedded _search_query from the object data
        $searchQuery = $identificationData['_search_query'] ?? $identificationData;

        // Remove _search_query from identification data (it's not an identity field)
        unset($identificationData['_search_query']);

        $identityFields = $identityGroup['identity_fields'] ?? [];
        $name           = $this->resolveObjectName($identificationData, $identityFields);

        // If no name could be resolved, this means no data was found - return null
        if ($name === null) {
            static::logDebug("Identity extraction found no data for {$identityGroup['object_type']} - no identifiable name in response", [
                'identification_data' => $identificationData,
            ]);

            return null;
        }

        // Build extraction result with per-object search query
        $itemExtractionResult = [
            'data'         => $identificationData,
            'search_query' => $searchQuery,
        ];

        // Resolve duplicates using DuplicateRecordResolver
        $matchId = $this->resolveDuplicate(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            extractionResult: $itemExtractionResult,
            parentObjectId: $parentObjectId
        );

        // Create or use existing TeamObject
        $teamObject = $this->resolveOrCreateTeamObject(
            taskRun: $taskRun,
            objectType: $identityGroup['object_type'],
            identificationData: $identificationData,
            name: $name,
            existingId: $matchId,
            parentObjectId: $parentObjectId
        );

        // Store resolved object ID for dependent processes
        app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
            $taskRun,
            $identityGroup['object_type'],
            $teamObject->id,
            $level
        );

        // Store resolved object in input artifacts for child processes
        app(ResolvedObjectsService::class)->storeInProcessArtifacts($taskProcess, $identityGroup['object_type'], $teamObject->id);

        static::logDebug('Identity extraction completed', [
            'object_type'        => $identityGroup['object_type'],
            'object_id'          => $teamObject->id,
            'was_existing'       => $matchId !== null,
            'resolved_parent_id' => $parentObjectId,
        ]);

        // Build and attach output artifact
        app(ExtractionArtifactBuilder::class)->buildIdentityArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $identityGroup,
            extractionResult: $itemExtractionResult,
            level: $level,
            matchId: $matchId
        );

        return $teamObject;
    }

    /**
     * Execute array identity extraction - creates multiple TeamObjects from array data.
     *
     * For array-type identity groups (e.g., Diagnosis where fragment_selector has type: "array"),
     * this method iterates through each item in the extracted array, performs duplicate resolution
     * for each, creates/updates TeamObjects, and stores all resolved IDs.
     *
     * Returns the first created TeamObject (method signature requires single return type).
     */
    protected function executeArrayIdentityExtraction(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        array $extractionResult,
        int $level,
        ?int $parentObjectId
    ): ?TeamObject {
        $extractedData    = $extractionResult['data']           ?? [];
        $fragmentSelector = $identityGroup['fragment_selector'] ?? [];
        $identityFields   = $identityGroup['identity_fields']   ?? [];
        $objectType       = $identityGroup['object_type'];

        // Get the leaf key - the schema is now simplified to just { leaf_key: [...items...] }
        $leafKey = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);
        $items   = $extractedData[$leafKey] ?? [];

        if (!is_array($items) || empty($items) || !isset($items[0])) {
            static::logDebug('No array items found in identity extraction result');

            return null;
        }

        static::logDebug('Processing array identity extraction', [
            'item_count'  => count($items),
            'object_type' => $objectType,
            'parent_id'   => $parentObjectId,
        ]);

        $createdObjects  = [];
        $artifactBuilder = app(ExtractionArtifactBuilder::class);

        foreach ($items as $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            // Extract embedded _search_query from this item
            $searchQuery = $itemData['_search_query'] ?? $itemData;

            // Remove _search_query from item data (it's not an identity field)
            unset($itemData['_search_query']);

            // Resolve name for this item
            $name = $this->resolveObjectName($itemData, $identityFields);
            if ($name === null) {
                static::logDebug('Skipping array item with no identifiable name');

                continue;
            }

            // Build item-specific extraction result with per-object search query
            $itemExtractionResult = [
                'data'         => $itemData,
                'search_query' => $searchQuery,
            ];

            // Resolve duplicates for this item (scoped to parent)
            $matchId = $this->resolveDuplicate(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                identityGroup: $identityGroup,
                extractionResult: $itemExtractionResult,
                parentObjectId: $parentObjectId
            );

            // Create or use existing TeamObject
            $teamObject = $this->resolveOrCreateTeamObject(
                taskRun: $taskRun,
                objectType: $objectType,
                identificationData: $itemData,
                name: $name,
                existingId: $matchId,
                parentObjectId: $parentObjectId
            );

            $createdObjects[] = $teamObject;

            // Store resolved object ID for dependent processes
            app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
                $taskRun,
                $objectType,
                $teamObject->id,
                $level
            );

            // Build individual identity artifact for this item
            $artifactBuilder->buildIdentityArtifact(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                teamObject: $teamObject,
                group: $identityGroup,
                extractionResult: $itemExtractionResult,
                level: $level,
                matchId: $matchId
            );

            static::logDebug('Processed array identity item', [
                'object_id'   => $teamObject->id,
                'object_name' => $teamObject->name,
                'was_update'  => $matchId !== null,
            ]);
        }

        if (empty($createdObjects)) {
            return null;
        }

        // Store ALL resolved objects in input artifacts for child processes
        $objectIds = array_map(fn($obj) => $obj->id, $createdObjects);
        app(ResolvedObjectsService::class)->storeMultipleInProcessArtifacts($taskProcess, $objectType, $objectIds);

        static::logDebug('Array identity extraction completed', [
            'object_type'   => $objectType,
            'created_count' => count($createdObjects),
        ]);

        // Return first created object (method signature requires single TeamObject return)
        return $createdObjects[0];
    }

    /**
     * Extract identity fields with search query using LLM.
     */
    protected function extractIdentityWithSearchQuery(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $group,
        array $possibleParentIds = []
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent || !$schemaDefinition) {
            static::logDebug('Missing agent or schema definition for identity extraction');

            return [];
        }

        // Build the response schema for identity extraction
        $responseSchema = $this->buildExtractionResponseSchema(
            $schemaDefinition,
            $group,
            $possibleParentIds
        );

        // Build and run the LLM thread
        return $this->runExtractionThread(
            $taskRun,
            $taskProcess,
            $artifacts,
            $responseSchema,
            $possibleParentIds
        );
    }

    /**
     * Build the response schema for identity extraction.
     *
     * Creates a simplified schema at leaf level with embedded _search_query:
     * - data: { leaf_key: { fields..., _search_query: {...} } }
     * - parent_id: (optional) When multiple parent options exist
     *
     * The schema is simplified to only include the leaf-level objects, not the full hierarchy.
     * Each object includes an embedded _search_query property for per-object duplicate resolution.
     *
     * @param  array<int>  $possibleParentIds
     */
    protected function buildExtractionResponseSchema(
        SchemaDefinition $schemaDefinition,
        array $group,
        array $possibleParentIds
    ): array {
        $fragmentSelector = $group['fragment_selector'] ?? [];
        $identityFields   = $group['identity_fields']   ?? [];
        $objectType       = $group['object_type']       ?? '';

        // Build _search_query schema from identity fields (embedded in each object)
        // Pass schema to enable type-aware search queries for dates, booleans, numbers
        $searchQuerySchema = $this->buildSearchQuerySchema($identityFields, $schemaDefinition->schema);

        // Get the leaf key and build simplified schema
        $leafKey = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);

        // Build the leaf-level schema with embedded _search_query
        $leafSchema = $this->buildLeafSchemaWithSearchQuery(
            $schemaDefinition,
            $fragmentSelector,
            $searchQuerySchema,
            $leafKey
        );

        $responseSchema = [
            'type'       => 'object',
            'properties' => [
                'data' => [
                    'type'       => 'object',
                    'properties' => [
                        $leafKey => $leafSchema,
                    ],
                ],
            ],
            'required' => ['data'],
        ];

        // Add parent_id field when multiple parents exist
        if (count($possibleParentIds) > 1) {
            $responseSchema['properties']['parent_id'] = [
                'type'        => 'integer',
                'description' => 'The ID of the parent object this data belongs to',
            ];
            $responseSchema['required'][] = 'parent_id';
        }

        return $responseSchema;
    }

    /**
     * Build the leaf-level schema with embedded _search_query property.
     *
     * Navigates through the fragment selector to find the leaf schema,
     * then adds _search_query to each object.
     */
    protected function buildLeafSchemaWithSearchQuery(
        SchemaDefinition $schemaDefinition,
        array $fragmentSelector,
        array $searchQuerySchema,
        string $leafKey
    ): array {
        // Use applyFragmentSelector to get the full schema, then extract leaf level
        $jsonSchemaService = app(JsonSchemaService::class);
        $fullSchema        = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Navigate to the leaf schema
        $leafSchema = $this->extractLeafSchema($fullSchema, $fragmentSelector);

        // Add _search_query to the leaf schema (handling both object and array types)
        return $this->injectSearchQueryIntoSchema($leafSchema, $searchQuerySchema);
    }

    /**
     * Extract the leaf-level schema by navigating through the fragment selector hierarchy.
     *
     * For flat structures (where ALL children at root are scalar types),
     * returns the full schema since the root IS the leaf.
     */
    protected function extractLeafSchema(array $schema, array $fragmentSelector): array
    {
        $properties = $schema['properties']         ?? [];
        $children   = $fragmentSelector['children'] ?? [];

        if (empty($children)) {
            return $schema;
        }

        // Check if ALL children at root level are scalar types (flat structure)
        // If so, the root IS the leaf - return the full schema
        if (app(FragmentSelectorService::class)->hasOnlyScalarChildren($children)) {
            return $schema;
        }

        $key = array_key_first($children);

        if (!isset($properties[$key])) {
            return $schema;
        }

        $childSchema    = $properties[$key];
        $childSelector  = $children[$key];
        $childChildren  = $childSelector['children'] ?? [];

        // Check if we're at the leaf level (children are scalar types)
        $hasNestedStructure = false;
        foreach ($childChildren as $grandchild) {
            if (isset($grandchild['type']) && in_array($grandchild['type'], ['object', 'array'], true)) {
                $hasNestedStructure = true;
                break;
            }
        }

        if (!$hasNestedStructure) {
            // We're at leaf level, return this schema
            return $childSchema;
        }

        // For array types, we need to look at items
        if (($childSchema['type'] ?? '') === 'array') {
            $itemSchema = $childSchema['items'] ?? [];

            return $this->extractLeafSchema($itemSchema, $childSelector);
        }

        // Continue traversing for object types
        return $this->extractLeafSchema($childSchema, $childSelector);
    }

    /**
     * Inject _search_query property into the schema (handles both object and array types).
     */
    protected function injectSearchQueryIntoSchema(array $schema, array $searchQuerySchema): array
    {
        $type = $schema['type'] ?? 'object';

        if ($type === 'array') {
            // For arrays, add _search_query to each item
            $items = $schema['items'] ?? [];
            if (!empty($items)) {
                $itemProperties                    = $items['properties'] ?? [];
                $itemProperties['_search_query']   = $searchQuerySchema;
                $schema['items']['properties']     = $itemProperties;
            }
        } else {
            // For objects, add _search_query directly
            $properties                    = $schema['properties'] ?? [];
            $properties['_search_query']   = $searchQuerySchema;
            $schema['properties']          = $properties;
        }

        return $schema;
    }

    /**
     * Build the _search_query schema as an array of query objects from SPECIFIC to BROAD.
     *
     * The LLM will return MINIMUM 3 search queries ordered from most specific to least specific:
     * - Query 1: Most specific - use exact extracted values
     * - Query 2: Less specific - key identifying terms
     * - Query 3: Broadest - general concept only
     *
     * This enables efficient duplicate detection by checking for exact matches first,
     * then progressively broadening if needed.
     *
     * Field types are inferred from the schema definition:
     * - Strings: Use LIKE pattern with % wildcards
     * - Dates: Use operator-based comparison (=, <, >, <=, >=, between)
     * - Booleans: Use true/false directly
     * - Numbers/Integers: Use operator-based comparison (=, <, >, <=, >=, between)
     *
     * @param  array<string>  $identityFields
     * @param  array|null  $schema  The schema definition to determine field types
     * @return array{type: string, description: string, items: array, minItems: int}
     */
    protected function buildSearchQuerySchema(array $identityFields, ?array $schema = null): array
    {
        $properties = [];
        foreach ($identityFields as $field) {
            $properties[$field] = $this->buildFieldSearchSchema($field, $schema);
        }

        return [
            'type'        => 'array',
            'description' => 'MINIMUM 3 search queries ordered from MOST SPECIFIC to LEAST SPECIFIC (exact match first, then progressively broader). Purpose: Find existing records efficiently - we check for exact matches first, then broaden if needed. Query 1: Most specific - use exact extracted values. Query 2: Less specific - key identifying terms. Query 3: Broadest - general concept only. Example for "Dr. John Smith": [{name: "%Dr. John Smith%"}, {name: "%John Smith%"}, {name: "%Smith%"}].',
            'items'       => [
                'type'       => 'object',
                'properties' => $properties,
            ],
            'minItems'    => 3,
        ];
    }

    /**
     * Build the search schema for a single field based on its type.
     *
     * Field types:
     * - Strings: LIKE pattern with % wildcards
     * - Dates: Operator-based comparison with ISO format values
     * - Booleans: Direct true/false
     * - Numbers/Integers: Operator-based comparison
     */
    protected function buildFieldSearchSchema(string $fieldName, ?array $schema): array
    {
        $fieldType = $this->determineFieldType($fieldName, $schema);

        return match ($fieldType) {
            'date', 'date-time' => $this->buildDateSearchSchema($fieldName),
            'boolean'           => $this->buildBooleanSearchSchema($fieldName),
            'number', 'integer' => $this->buildNumericSearchSchema($fieldName, $fieldType),
            default             => $this->buildStringSearchSchema($fieldName),
        };
    }

    /**
     * Determine the type of a field from the schema definition.
     *
     * Delegates to SchemaFieldHelper trait with special handling for native TeamObject columns.
     */
    protected function determineFieldType(string $fieldName, ?array $schema): string
    {
        // Handle native TeamObject columns
        if ($fieldName === 'name') {
            return 'string';
        }
        if ($fieldName === 'date') {
            return 'date';
        }

        // Delegate to trait for schema-based field type detection
        return $this->getSchemaFieldType($fieldName, $schema);
    }

    /**
     * Build search schema for string fields (keyword array).
     *
     * Returns a schema for keyword arrays where ALL keywords must be present (AND logic)
     * but order doesn't matter. This enables flexible matching:
     * - ['neck', 'pain', 'cervical'] matches 'Cervical neck pain', 'Neck pain - cervical', etc.
     */
    protected function buildStringSearchSchema(string $fieldName): array
    {
        return [
            'type'        => ['array', 'null'],
            'items'       => ['type' => 'string'],
            'description' => "Keywords to search for in {$fieldName} (ALL must be present, order doesn't matter). Example: ['neck', 'pain', 'cervical'] matches 'Cervical neck pain', 'Neck pain - cervical', etc. Use 2-4 keywords for specific searches, 1-2 for broad searches. Set to null to skip this field.",
        ];
    }

    /**
     * Build search schema for date fields (operator-based).
     */
    protected function buildDateSearchSchema(string $fieldName): array
    {
        return [
            'type'        => ['object', 'null'],
            'properties'  => [
                'operator' => [
                    'type'        => 'string',
                    'enum'        => ['=', '<', '>', '<=', '>=', 'between'],
                    'description' => 'Comparison operator',
                ],
                'value'    => [
                    'type'        => 'string',
                    'description' => 'Date value in ISO format YYYY-MM-DD',
                ],
                'value2'   => [
                    'type'        => 'string',
                    'description' => "End date for 'between' operator in ISO format YYYY-MM-DD",
                ],
            ],
            'required'    => ['operator', 'value'],
            'description' => "Date comparison for {$fieldName}. Use operator with ISO date value (YYYY-MM-DD). Examples: {\"operator\": \"=\", \"value\": \"2017-10-23\"} for exact match, {\"operator\": \"between\", \"value\": \"2017-01-01\", \"value2\": \"2017-12-31\"} for range. Set to null to skip.",
        ];
    }

    /**
     * Build search schema for boolean fields.
     */
    protected function buildBooleanSearchSchema(string $fieldName): array
    {
        return [
            'type'        => ['boolean', 'null'],
            'description' => "Boolean value for {$fieldName}. Use true or false directly. Set to null to skip.",
        ];
    }

    /**
     * Build search schema for numeric fields (operator-based).
     */
    protected function buildNumericSearchSchema(string $fieldName, string $numericType): array
    {
        $valueType = $numericType === 'integer' ? 'integer' : 'number';

        return [
            'type'        => ['object', 'null'],
            'properties'  => [
                'operator' => [
                    'type'        => 'string',
                    'enum'        => ['=', '<', '>', '<=', '>=', 'between'],
                    'description' => 'Comparison operator',
                ],
                'value'    => [
                    'type'        => $valueType,
                    'description' => 'Numeric value to compare',
                ],
                'value2'   => [
                    'type'        => $valueType,
                    'description' => "End value for 'between' operator",
                ],
            ],
            'required'    => ['operator', 'value'],
            'description' => "Numeric comparison for {$fieldName}. Use operator with {$numericType} value. Examples: {\"operator\": \"=\", \"value\": 42} for exact match, {\"operator\": \">=\", \"value\": 18} for minimum, {\"operator\": \"between\", \"value\": 10, \"value2\": 100} for range. Set to null to skip.",
        ];
    }

    /**
     * Run the LLM extraction thread and return parsed results.
     *
     * The response contains data with embedded _search_query in each object.
     * NOTE: search_query is no longer a top-level field - it's now embedded as _search_query in each object.
     *
     * @param  array<int>  $possibleParentIds
     * @return array{data: array, parent_id: int|null}
     */
    protected function runExtractionThread(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $responseSchema,
        array $possibleParentIds
    ): array {
        $taskDefinition = $taskRun->taskDefinition;

        // Build thread with artifacts and optional parent context
        $thread = $this->buildExtractionThread($taskRun, $artifacts, $possibleParentIds);

        // Associate thread with task process for debugging
        $taskProcess->agentThread()->associate($thread)->save();

        // Get timeout from config (default 5 minutes for large extractions)
        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 300;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Create a temporary in-memory SchemaDefinition for the identity extraction response
        $identitySchema = $this->createIdentityExtractionSchema($responseSchema);

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($identitySchema, null, app(JsonSchemaService::class))
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Identity extraction thread failed', [
                'error' => $threadRun->error ?? 'Unknown',
            ]);

            return [];
        }

        $data = $threadRun->lastMessage?->getJsonContent();

        if (!is_array($data)) {
            return [];
        }

        // Note: _search_query is now embedded in each object within data,
        // not returned as a separate top-level field
        return [
            'data'      => $data['data']      ?? [],
            'parent_id' => $data['parent_id'] ?? null,
        ];
    }

    /**
     * Build the agent thread for identity extraction.
     *
     * @param  array<int>  $possibleParentIds
     */
    protected function buildExtractionThread(
        TaskRun $taskRun,
        Collection $artifacts,
        array $possibleParentIds
    ): AgentThread {
        $taskDefinition = $taskRun->taskDefinition;

        // Build additional context for parent hierarchy from database
        $parentContext = $this->buildParentContextForMultipleParents($possibleParentIds);

        $threadBuilder = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Identity Data Extraction')
            ->withArtifacts($artifacts, new ArtifactFilter(
                includeFiles: false,
                includeJson: false,
                includeMeta: false
            ));

        // Add parent context as a system message when available
        if ($parentContext !== '') {
            $threadBuilder->withSystemMessage($parentContext);
        }

        return $threadBuilder->build();
    }

    /**
     * Resolve duplicate using DuplicateRecordResolver.
     *
     * The exact match check is performed inside findCandidates(), which returns
     * an exactMatchId when found. If no exact match, falls back to LLM resolution.
     */
    protected function resolveDuplicate(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        array $extractionResult,
        ?int $parentObjectId
    ): ?int {
        $extractedData = $extractionResult['data']         ?? [];
        $searchQuery   = $extractionResult['search_query'] ?? [];

        if (empty($extractedData)) {
            return null;
        }

        // The LLM returns an array of queries from most to least restrictive.
        // Normalize to ensure we have the expected array format.
        $searchQueries = $this->normalizeSearchQueries($searchQuery, $extractedData);

        $resolver = app(DuplicateRecordResolver::class);
        $result   = $resolver->findCandidates(
            objectType: $identityGroup['object_type'],
            searchQueries: $searchQueries,
            parentObjectId: $parentObjectId,
            schemaDefinitionId: $taskRun->taskDefinition->schema_definition_id,
            extractedData: $extractedData,
            identityFields: $identityGroup['identity_fields'] ?? []
        );

        // If exact match was found during candidate search, return immediately
        if ($result->hasExactMatch()) {
            static::logDebug('Exact match found during candidate search', ['object_id' => $result->exactMatchId]);

            return $result->exactMatchId;
        }

        if ($result->candidates->isEmpty()) {
            return null;
        }

        // LLM Call #2: Resolve which candidate (if any) matches
        $resolution = $resolver->resolveDuplicate(
            extractedData: $extractedData,
            candidates: $result->candidates,
            taskRun: $taskRun,
            taskProcess: $taskProcess
        );

        if ($resolution->hasDuplicate()) {
            static::logDebug('LLM resolution found match', ['object_id' => $resolution->existingObjectId]);

            return $resolution->existingObjectId;
        }

        return null;
    }

    /**
     * Normalize search queries to array format.
     *
     * The LLM returns _search_query as an array of query objects from most to least restrictive.
     * If the array is empty or malformed, creates a fallback query from extracted data.
     *
     * @param  array  $searchQuery  Raw search query data from extraction
     * @param  array  $extractedData  The extracted identity data as fallback
     * @return array<array> Array of search query objects
     */
    protected function normalizeSearchQueries(array $searchQuery, array $extractedData): array
    {
        // If already an array of query objects, use as-is
        if (!empty($searchQuery) && isset($searchQuery[0]) && is_array($searchQuery[0])) {
            return $searchQuery;
        }

        // Create a fallback query from extracted data using LIKE patterns for loose matching
        $singleQuery = [];
        foreach ($extractedData as $field => $value) {
            if (is_string($value) && $value !== '') {
                $singleQuery[$field] = '%' . $value . '%';
            }
        }

        return !empty($singleQuery) ? [$singleQuery] : [];
    }

    /**
     * Resolve existing or create new TeamObject.
     */
    protected function resolveOrCreateTeamObject(
        TaskRun $taskRun,
        string $objectType,
        array $identificationData,
        string $name,
        ?int $existingId,
        ?int $parentObjectId
    ): TeamObject {
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);

        if ($taskRun->taskDefinition->schemaDefinition) {
            $mapper->setSchemaDefinition($taskRun->taskDefinition->schemaDefinition);
        }

        if ($existingId) {
            $teamObject = TeamObject::find($existingId);
            if ($teamObject) {
                // Update existing object with new data
                $mapper->updateTeamObject($teamObject, $identificationData);
                $this->saveIdentityFieldsAsAttributes($mapper, $teamObject, $identificationData);

                return $teamObject;
            }
        }

        // Set up parent object context if needed
        if ($parentObjectId) {
            $parentObject = TeamObject::find($parentObjectId);
            if ($parentObject) {
                $mapper->setRootObject($parentObject);
            }
        }

        // Ensure the identificationData has the resolved name to prevent empty string from being
        // filled by updateTeamObject (which is called by createTeamObject)
        $identificationData['name'] = $name;

        $teamObject = $mapper->createTeamObject($objectType, $name, $identificationData);

        $this->saveIdentityFieldsAsAttributes($mapper, $teamObject, $identificationData);

        return $teamObject;
    }

    /**
     * Save identity fields that are not TeamObject columns as attributes.
     *
     * Fields that match TeamObject columns (name, date, description, url) are already
     * saved directly on the model by updateTeamObject/createTeamObject. This method
     * saves all other identity fields as team_object_attributes.
     *
     * Date fields are normalized to ISO format (YYYY-MM-DD) based on the schema definition.
     */
    protected function saveIdentityFieldsAsAttributes(
        JSONSchemaDataToDatabaseMapper $mapper,
        TeamObject $teamObject,
        array $identificationData
    ): void {
        // Fields that are columns on TeamObject model (already handled by updateTeamObject)
        $teamObjectColumns = ['name', 'date', 'description', 'url'];

        // Get schema for date field detection
        $schema = $teamObject->schemaDefinition?->schema;

        foreach ($identificationData as $fieldName => $fieldValue) {
            // Skip if this is a TeamObject column (already saved on model)
            if (in_array($fieldName, $teamObjectColumns, true)) {
                continue;
            }

            // Skip null/empty values
            if ($fieldValue === null || $fieldValue === '') {
                continue;
            }

            // Normalize date values to ISO format (YYYY-MM-DD) before saving
            if ($this->isDateField($fieldName, $schema) && is_string($fieldValue)) {
                $fieldValue = $this->normalizeDateValue($fieldValue);
            }

            static::logDebug("Saving identity field as attribute: {$fieldName}", [
                'team_object_id' => $teamObject->id,
                'value'          => $fieldValue,
            ]);

            $mapper->saveTeamObjectAttribute($teamObject, $fieldName, [
                'value' => $fieldValue,
            ]);
        }
    }

    /**
     * Resolve a name for the TeamObject from identification data.
     * Returns null if no usable name can be found (indicating no data was extracted).
     *
     * Prefers the literal 'name' field if it exists and has a value, then falls back
     * to iterating through identity_fields in order. This prevents cases where
     * identity_fields = ["date", "name"] would incorrectly use the date as the name.
     */
    protected function resolveObjectName(array $identificationData, array $identityFields): ?string
    {
        // Prefer the literal 'name' field if it exists and has a value
        if (!empty($identificationData['name']) && is_string($identificationData['name'])) {
            return $identificationData['name'];
        }

        // Fall back to first non-empty identity field
        foreach ($identityFields as $field) {
            if ($field === 'name') {
                continue; // Already checked above
            }
            if (!empty($identificationData[$field]) && is_string($identificationData[$field])) {
                return $identificationData[$field];
            }
        }

        return null;
    }

    /**
     * Create a transient SchemaDefinition for identity extraction response format.
     */
    protected function createIdentityExtractionSchema(array $responseSchema): SchemaDefinition
    {
        $schemaDefinition         = new SchemaDefinition;
        $schemaDefinition->schema = $responseSchema;
        $schemaDefinition->name   = 'IdentityExtractionResponse';
        $schemaDefinition->type   = SchemaDefinition::TYPE_AGENT_RESPONSE;

        return $schemaDefinition;
    }

    /**
     * Build parent context prompt for multiple parent options using database lookups.
     *
     * When there are multiple possible parents, fetches each parent's ancestor chain
     * from the database to provide rich context for LLM resolution.
     *
     * @param  array<int>  $parentIds
     */
    protected function buildParentContextForMultipleParents(array $parentIds): string
    {
        if (count($parentIds) <= 1) {
            return '';
        }

        $prompt = "\n\nPARENT OPTIONS:\n";
        $prompt .= "Choose which parent this data belongs to by setting parent_id in your response.\n\n";

        foreach ($parentIds as $parentId) {
            $parent = TeamObject::find($parentId);

            if (!$parent) {
                continue;
            }

            $prompt .= "Option - ID {$parentId}: {$parent->name} (Type: {$parent->type})\n";

            // Get ancestors from DB
            $ancestors = $this->getAncestorChain($parent);

            if (!empty($ancestors)) {
                $prompt        .= '  Hierarchy: ';
                $ancestorNames = array_map(fn($a) => "{$a->type}: {$a->name}", $ancestors);
                $prompt        .= implode(' -> ', $ancestorNames) . " -> {$parent->name}\n";
            }

            $prompt .= "\n";
        }

        return $prompt;
    }
}
