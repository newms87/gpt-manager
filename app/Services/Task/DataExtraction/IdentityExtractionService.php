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
        [$parentObjectId, $possibleParentIds] = $this->resolveParentContext($artifacts, $identityGroup, $level);

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
     * Resolve parent object context from artifacts and identity group configuration.
     *
     * Returns [resolvedParentId, possibleParentIds] tuple:
     * - resolvedParentId: Single parent ID if determinable, null if multiple or none
     * - possibleParentIds: Array of all possible parent IDs for LLM resolution
     *
     * @return array{?int, array<int>}
     */
    protected function resolveParentContext(Collection $artifacts, array $identityGroup, int $level): array
    {
        // Get parent type from fragment_selector (second-to-last key in path)
        $fragmentSelector = $identityGroup['fragment_selector'] ?? [];
        $parentType       = app(FragmentSelectorService::class)->getParentType($fragmentSelector);

        // Combine resolved_objects from ALL input artifacts
        $combinedResolvedObjects = app(ResolvedObjectsService::class)->combineFromArtifacts($artifacts);
        $possibleParentIds       = $combinedResolvedObjects[$parentType] ?? [];

        // Determine if this is a root object (no parent type = fragment_selector path < 2)
        $isRootObject = $parentType === null;

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
     * Returns the first created TeamObject for backwards compatibility.
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
        $searchQuerySchema = $this->buildSearchQuerySchema($identityFields);

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
     * Build the _search_query schema as an array of query objects from least to most restrictive.
     *
     * The LLM will return multiple search queries where:
     * - First query uses only the most identifying field (usually name)
     * - Subsequent queries add more fields to narrow results
     *
     * @param  array<string>  $identityFields
     * @return array{type: string, description: string, items: array}
     */
    protected function buildSearchQuerySchema(array $identityFields): array
    {
        $properties = [];
        foreach ($identityFields as $field) {
            $properties[$field] = [
                'type'        => ['string', 'null'],
                'description' => "SQL LIKE pattern for searching {$field} (use % wildcards), or null to skip this field",
            ];
        }

        return [
            'type'        => 'array',
            'description' => 'Array of search queries from LEAST to MOST restrictive. First query should use only the most identifying field (usually name). Add more fields in subsequent queries only if needed to narrow results. Use SQL LIKE patterns with % wildcards. Set field to null to skip it in that query.',
            'items'       => [
                'type'       => 'object',
                'properties' => $properties,
            ],
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
     * Performs quick exact-match first (optimization), then falls back to LLM resolution.
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

        // Convert search query to array format if needed
        // The LLM returns an array of queries from least to most restrictive
        // If we got a single object (backwards compat), wrap it in an array
        $searchQueries = $this->normalizeSearchQueries($searchQuery, $extractedData);

        $resolver   = app(DuplicateRecordResolver::class);
        $candidates = $resolver->findCandidates(
            objectType: $identityGroup['object_type'],
            searchQueries: $searchQueries,
            parentObjectId: $parentObjectId,
            schemaDefinitionId: $taskRun->taskDefinition->schema_definition_id
        );

        if ($candidates->isEmpty()) {
            return null;
        }

        // Try quick exact-match first (optimization - avoids LLM call)
        $identityFields = $identityGroup['identity_fields'] ?? [];
        $quickMatch     = $resolver->quickMatchCheck($extractedData, $candidates, $identityFields);
        if ($quickMatch) {
            static::logDebug('Quick exact match found', ['object_id' => $quickMatch->id]);

            return $quickMatch->id;
        }

        // LLM Call #2: Resolve which candidate (if any) matches
        $result = $resolver->resolveDuplicate(
            extractedData: $extractedData,
            candidates: $candidates,
            taskRun: $taskRun,
            taskProcess: $taskProcess
        );

        if ($result->hasDuplicate()) {
            static::logDebug('LLM resolution found match', ['object_id' => $result->existingObjectId]);

            return $result->existingObjectId;
        }

        return null;
    }

    /**
     * Normalize search queries to array format.
     *
     * The LLM returns _search_query as an array of query objects from least to most restrictive.
     * If we receive a single object (old format) or empty array, create a reasonable fallback.
     *
     * @param  array  $searchQuery  Raw search query data from extraction
     * @param  array  $extractedData  The extracted identity data as fallback
     * @return array<array> Array of search query objects
     */
    protected function normalizeSearchQueries(array $searchQuery, array $extractedData): array
    {
        // If already an array of query objects (new format), use as-is
        if (!empty($searchQuery) && isset($searchQuery[0]) && is_array($searchQuery[0])) {
            return $searchQuery;
        }

        // If empty or single object format, create a single query from extracted data
        // Use LIKE patterns for loose matching
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
     */
    protected function saveIdentityFieldsAsAttributes(
        JSONSchemaDataToDatabaseMapper $mapper,
        TeamObject $teamObject,
        array $identificationData
    ): void {
        // Fields that are columns on TeamObject model (already handled by updateTeamObject)
        $teamObjectColumns = ['name', 'date', 'description', 'url'];

        foreach ($identificationData as $fieldName => $fieldValue) {
            // Skip if this is a TeamObject column (already saved on model)
            if (in_array($fieldName, $teamObjectColumns, true)) {
                continue;
            }

            // Skip null/empty values
            if ($fieldValue === null || $fieldValue === '') {
                continue;
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
     * Only checks fields specified in $identityFields - no hardcoded fallbacks.
     */
    protected function resolveObjectName(array $identificationData, array $identityFields): ?string
    {
        foreach ($identityFields as $field) {
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
