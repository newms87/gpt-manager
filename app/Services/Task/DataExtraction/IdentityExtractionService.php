<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use App\Traits\MergesExtractionResults;
use App\Traits\SchemaFieldHelper;
use App\Traits\TeamObjectRelationshipHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

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
 * Response schema structure (top-level pattern):
 * {
 *   "data": { leaf_key: { extracted fields... } },
 *   "page_sources": { "field_name": 1, ... },
 *   "search_query": [ { field: keywords }, ... ],
 *   "confidence": { "field_name": 4, ... },  // optional
 *   "parent_id": 123  // optional, when multiple parents
 * }
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
    use MergesExtractionResults;
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

        // Get all artifacts from parent output artifact for context expansion
        $allArtifacts = app(ExtractionProcessOrchestrator::class)->getAllPageArtifacts($taskRun);

        // Resolve parent object context
        [$parentObjectId, $possibleParentIds] = $this->resolveParentContext($taskProcess, $identityGroup, $level);

        // Determine if this is an array-type extraction BEFORE calling LLM
        // This allows us to skip search_query in initial extraction for arrays
        $fragmentSelectorService = app(FragmentSelectorService::class);
        $isLeafArrayType         = $fragmentSelectorService->isLeafArrayType($identityGroup);

        static::logDebug('Running identity extraction', [
            'level'                  => $level,
            'group'                  => $identityGroup['name'] ?? 'unknown',
            'artifact_count'         => $artifacts->count(),
            'possible_parents_count' => count($possibleParentIds),
            'is_leaf_array_type'     => $isLeafArrayType,
        ]);

        // LLM Call: Extract identity fields + search query (single objects) or identity fields only (arrays)
        // For array extractions, search queries are generated via SearchQueryGenerationService to reduce schema complexity
        $extractionResult = $this->extractIdentityWithSearchQuery(
            $taskRun,
            $taskProcess,
            $artifacts,
            $identityGroup,
            $possibleParentIds,
            $allArtifacts,
            includeSearchQuery: !$isLeafArrayType
        );

        if (empty($extractionResult)) {
            static::logDebug('No identity data extracted');

            return null;
        }

        // Use LLM-resolved parent_id when available (from multiple parent resolution),
        // otherwise use the pre-resolved parentObjectId from resolved_objects
        $resolvedParentId = $extractionResult['parent_id'] ?? $parentObjectId;

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
        // and stored in the config artifact's meta field
        $config            = app(ProcessConfigArtifactService::class)->getConfigFromProcess($taskProcess);
        $possibleParentIds = $config['parent_object_ids'] ?? [];

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
        // The data is now simplified to leaf level only: { leaf_key: { fields... } }
        // We need to extract the leaf key's content directly
        $fragmentSelector = $identityGroup['fragment_selector'] ?? [];
        $objectType       = $identityGroup['object_type']       ?? '';
        $leafKey          = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);

        // Get the data for the leaf key (no complex unwrapping needed - schema is already simplified)
        $extractedData      = $extractionResult['data'] ?? [];
        $identificationData = $extractedData[$leafKey]  ?? [];

        // Get page_sources and search_query from top-level (new pattern)
        $pageSources  = $extractionResult['page_sources']  ?? [];
        $searchQuery  = $extractionResult['search_query']  ?? [];

        // If identification data is not an array, we cannot extract identity fields - return null
        if (!is_array($identificationData)) {
            static::logDebug("Identity extraction found non-array data for {$identityGroup['object_type']} - cannot extract identity fields", [
                'identification_data' => $identificationData,
            ]);

            return null;
        }

        $identityFields = $identityGroup['identity_fields'] ?? [];
        $name           = $this->resolveObjectName($identificationData, $identityFields);

        // If no name could be resolved, this means no data was found - return null
        if ($name === null) {
            static::logDebug("Identity extraction found no data for {$identityGroup['object_type']} - no identifiable name in response", [
                'identification_data' => $identificationData,
            ]);

            return null;
        }

        // Add name to identification data BEFORE duplicate resolution
        // DuplicateRecordResolver.findCandidates() uses extractedData['name'] for exact matching
        $identificationData['name'] = $name;

        // Build extraction result with per-object search query
        $itemExtractionResult = [
            'data'         => $identificationData,
            'search_query' => $searchQuery,
        ];

        // Resolve duplicates using DuplicateRecordResolver
        $resolution = $this->resolveDuplicate(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            extractionResult: $itemExtractionResult,
            parentObjectId: $parentObjectId
        );

        // Extract matchId for downstream use
        $matchId = $resolution->hasDuplicate() ? $resolution->existingObjectId : null;

        // Merge updated values into identification data when LLM found better values
        // This allows updating the existing record with more complete data
        if ($resolution->hasUpdatedValues()) {
            $identificationData = array_merge($identificationData, $resolution->getUpdatedValues());

            static::logDebug('Merging updated values from duplicate resolution', [
                'updated_fields' => array_keys($resolution->getUpdatedValues()),
            ]);
        }

        // Get relationship key from fragment_selector (schema is source of truth)
        $relationshipKey = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);

        // Create or use existing TeamObject
        $teamObject = $this->resolveOrCreateTeamObject(
            taskRun: $taskRun,
            objectType: $identityGroup['object_type'],
            identificationData: $identificationData,
            name: $name,
            existingId: $matchId,
            parentObjectId: $parentObjectId,
            relationshipKey: $relationshipKey
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
            'object_type'          => $identityGroup['object_type'],
            'object_id'            => $teamObject->id,
            'was_existing'         => $matchId !== null,
            'resolved_parent_id'   => $parentObjectId,
            'had_updated_values'   => $resolution->hasUpdatedValues(),
        ]);

        // Build and attach output artifact(s)
        // Pass the explicit parent object to ensure correct parent linkage in artifacts
        $parentObject = $parentObjectId ? TeamObject::find($parentObjectId) : null;
        app(ExtractionArtifactBuilder::class)->buildIdentityArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $identityGroup,
            extractionResult: $itemExtractionResult,
            level: $level,
            matchId: $matchId,
            pageSources: !empty($pageSources) ? $pageSources : null,
            parentObject: $parentObject
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
     * The initial extraction does NOT include search queries (to reduce schema complexity).
     * After extraction, a follow-up request to SearchQueryGenerationService generates
     * item-specific search queries for duplicate detection.
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

        // Get top-level page_sources (search_query is now generated via follow-up request)
        $pageSources = $extractionResult['page_sources'] ?? [];

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

        // Filter to only valid array items for search query generation
        $validItems = [];
        foreach ($items as $index => $itemData) {
            if (is_array($itemData)) {
                $validItems[$index] = $itemData;
            }
        }

        // Generate item-specific search queries in batch via follow-up request
        $indexedSearchQueries = app(SearchQueryGenerationService::class)->generateForArrayItems(
            $taskProcess,
            $validItems,
            $identityFields
        );

        static::logDebug('Generated search queries for array items', [
            'item_count'              => count($validItems),
            'search_queries_received' => count(array_filter($indexedSearchQueries)),
        ]);

        $createdObjects  = [];
        $artifactBuilder = app(ExtractionArtifactBuilder::class);

        // Load parent object once for all items to ensure correct parent linkage in artifacts
        $parentObject = $parentObjectId ? TeamObject::find($parentObjectId) : null;

        foreach ($items as $index => $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            // Extract page sources for this item using dot notation keys
            $itemPageSources = $this->extractItemPageSources($pageSources, $leafKey, $index);

            // Use item-specific search queries generated via SearchQueryGenerationService
            $searchQuery = $indexedSearchQueries[$index] ?? [];

            // Resolve name for this item
            $name = $this->resolveObjectName($itemData, $identityFields);
            if ($name === null) {
                static::logDebug('Skipping array item with no identifiable name');

                continue;
            }

            // Add name to item data BEFORE duplicate resolution
            // DuplicateRecordResolver.findCandidates() uses extractedData['name'] for exact matching
            $itemData['name'] = $name;

            // Build item-specific extraction result with per-object search query
            $itemExtractionResult = [
                'data'         => $itemData,
                'search_query' => $searchQuery,
            ];

            // Resolve duplicates for this item (scoped to parent)
            $resolution = $this->resolveDuplicate(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                identityGroup: $identityGroup,
                extractionResult: $itemExtractionResult,
                parentObjectId: $parentObjectId
            );

            // Extract matchId for downstream use
            $matchId = $resolution->hasDuplicate() ? $resolution->existingObjectId : null;

            // Merge updated values into item data when LLM found better values
            // This allows updating the existing record with more complete data
            if ($resolution->hasUpdatedValues()) {
                $itemData = array_merge($itemData, $resolution->getUpdatedValues());

                static::logDebug('Merging updated values from duplicate resolution', [
                    'updated_fields' => array_keys($resolution->getUpdatedValues()),
                ]);
            }

            // Create or use existing TeamObject
            // $leafKey is the relationship key from the schema (computed at start of method)
            $teamObject = $this->resolveOrCreateTeamObject(
                taskRun: $taskRun,
                objectType: $objectType,
                identificationData: $itemData,
                name: $name,
                existingId: $matchId,
                parentObjectId: $parentObjectId,
                relationshipKey: $leafKey
            );

            $createdObjects[] = $teamObject;

            // Store resolved object ID for dependent processes
            app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
                $taskRun,
                $objectType,
                $teamObject->id,
                $level
            );

            // Build individual identity artifact(s) for this item
            $artifactBuilder->buildIdentityArtifact(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                teamObject: $teamObject,
                group: $identityGroup,
                extractionResult: $itemExtractionResult,
                level: $level,
                matchId: $matchId,
                pageSources: !empty($itemPageSources) ? $itemPageSources : null,
                parentObject: $parentObject
            );

            static::logDebug('Processed array identity item', [
                'object_id'          => $teamObject->id,
                'object_name'        => $teamObject->name,
                'was_update'         => $matchId !== null,
                'had_updated_values' => $resolution->hasUpdatedValues(),
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
     * Extract page sources for a specific array item using dot notation.
     *
     * Page sources use dot notation like "diagnoses[0].name": 1, "diagnoses[1].name": 2
     * This method extracts sources relevant to a specific index.
     *
     * @param  array  $pageSources  Full page sources map
     * @param  string  $leafKey  The array field name (e.g., "diagnoses")
     * @param  int  $index  The array index
     * @return array Page sources for this item with normalized keys (without array prefix)
     */
    protected function extractItemPageSources(array $pageSources, string $leafKey, int $index): array
    {
        $prefix      = "{$leafKey}[{$index}].";
        $prefixLen   = strlen($prefix);
        $itemSources = [];

        foreach ($pageSources as $key => $pageNumber) {
            if (str_starts_with($key, $prefix)) {
                // Strip the prefix to get the field name
                $fieldName               = substr($key, $prefixLen);
                $itemSources[$fieldName] = $pageNumber;
            }
        }

        return $itemSources;
    }

    /**
     * Extract identity fields with search query using LLM.
     *
     * Both skim and exhaustive modes use the same batch loop:
     * - Skim mode ($stopOnConfidence = true): breaks early when all identity fields have sufficient confidence
     * - Exhaustive mode ($stopOnConfidence = false): processes all batches without early stopping
     *
     * For array extractions, search queries are omitted from the initial extraction
     * and generated via a follow-up request to SearchQueryGenerationService.
     *
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @param  bool  $includeSearchQuery  When true, includes search_query in schema (default true for single objects, false for arrays)
     * @return array{data: array, parent_id: int|null, page_sources: array, search_query: array}
     */
    protected function extractIdentityWithSearchQuery(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $group,
        array $possibleParentIds = [],
        ?Collection $allArtifacts = null,
        bool $includeSearchQuery = true
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent || !$schemaDefinition) {
            static::logDebug('Missing agent or schema definition for identity extraction');

            return [];
        }

        // Determine if we should stop early based on search mode
        $searchMode       = $group['search_mode'] ?? 'exhaustive';
        $stopOnConfidence = $searchMode === 'skim';

        $config              = $taskRun->taskDefinition->task_runner_config;
        $confidenceThreshold = $config['confidence_threshold'] ?? 3;
        $batchSize           = $config['batch_size']           ?? 5;
        $identityFields      = $group['identity_fields']       ?? [];

        $cumulativeData        = [];
        $cumulativeConfidence  = [];
        $cumulativePageSources = [];
        $cumulativeSearchQuery = [];
        $cumulativeConflicts   = [];
        $resolvedParentId      = null;

        // Filter out the Process Config artifact - it should not be included in batch processing
        // The config artifact is used for configuration, not for LLM extraction
        $pageArtifacts = $artifacts->filter(
            fn(Artifact $artifact) => $artifact->name !== ProcessConfigArtifactService::CONFIG_ARTIFACT_NAME
        );

        static::logDebug('Starting identity extraction', [
            'artifact_count'       => $pageArtifacts->count(),
            'stop_on_confidence'   => $stopOnConfidence,
            'confidence_threshold' => $stopOnConfidence ? $confidenceThreshold : 'N/A',
            'batch_size'           => $batchSize,
            'identity_fields'      => $identityFields,
        ]);

        // Process artifacts in batches
        foreach ($pageArtifacts->chunk($batchSize) as $batchIndex => $batch) {
            static::logDebug("Processing identity extraction batch $batchIndex with " . $batch->count() . ' artifacts');

            $batchResult = $this->runExtractionOnBatch(
                $taskRun,
                $taskProcess,
                $batch,
                $group,
                $possibleParentIds,
                includeConfidence: $stopOnConfidence,
                allArtifacts: $allArtifacts,
                includeSearchQuery: $includeSearchQuery
            );

            // Merge batch data with cumulative data, tracking updates AND detecting conflicts
            // A conflict occurs when both batches have meaningful but different values
            $batchData        = $batchResult['data']         ?? [];
            $batchPageSources = $batchResult['page_sources'] ?? [];

            $mergeResult = $this->mergeExtractionResultsWithConflicts(
                $cumulativeData,
                $batchData,
                $cumulativePageSources,
                $batchPageSources
            );

            $cumulativeData      = $mergeResult['merged'];
            $cumulativeConflicts = array_merge($cumulativeConflicts, $mergeResult['conflicts']);

            // Use first parent_id found (from first batch that resolves it)
            if ($resolvedParentId === null && !empty($batchResult['parent_id'])) {
                $resolvedParentId = $batchResult['parent_id'];
            }

            // Only merge page sources for fields that were actually updated
            // This prevents later batches with empty data from overwriting page_sources
            $cumulativePageSources = $this->mergePageSourcesForUpdatedFields(
                $cumulativePageSources,
                $batchPageSources,
                $mergeResult['updated_fields']
            );

            // Take the most complete search query (first non-empty) - only when includeSearchQuery is true
            if ($includeSearchQuery && empty($cumulativeSearchQuery) && !empty($batchResult['search_query'])) {
                $cumulativeSearchQuery = $batchResult['search_query'];
            }

            // Update confidence scores (take the highest confidence for each field)
            foreach ($batchResult['confidence'] ?? [] as $field => $score) {
                if (!isset($cumulativeConfidence[$field]) || $score > $cumulativeConfidence[$field]) {
                    $cumulativeConfidence[$field] = $score;
                }
            }

            // Check if we should stop early (skim mode only)
            if ($stopOnConfidence && $this->allFieldsHaveHighConfidence($identityFields, $cumulativeConfidence, $confidenceThreshold)) {
                $highConfidenceFields = array_filter($cumulativeConfidence, fn($score) => $score >= $confidenceThreshold);
                static::logDebug('Stopping early - all identity fields have sufficient confidence', [
                    'batches_processed'      => $batchIndex + 1,
                    'high_confidence_fields' => array_keys($highConfidenceFields),
                    'confidence_scores'      => $cumulativeConfidence,
                ]);
                break;
            }
        }

        // Resolve conflicts if any exist - make a follow-up LLM call with relevant source pages
        if (!empty($cumulativeConflicts) && $allArtifacts !== null) {
            static::logDebug('Resolving batch conflicts', [
                'conflict_count' => count($cumulativeConflicts),
                'fields'         => array_column($cumulativeConflicts, 'field_name'),
            ]);

            $resolution = app(ConflictResolutionService::class)->resolveConflicts(
                $taskRun,
                $taskProcess,
                $cumulativeConflicts,
                $allArtifacts,
                $schemaDefinition->schema ?? []
            );

            // Apply resolved values to cumulative data
            $cumulativeData = $this->applyResolvedValues($cumulativeData, $resolution['resolved_data']);

            // Update page sources for resolved fields
            $cumulativePageSources = array_merge($cumulativePageSources, $resolution['resolved_page_sources']);
        }

        return [
            'data'         => $cumulativeData,
            'parent_id'    => $resolvedParentId,
            'page_sources' => $cumulativePageSources,
            'search_query' => $cumulativeSearchQuery,
        ];
    }

    /**
     * Apply resolved conflict values to the data array.
     *
     * Recursively finds and updates fields by name in the nested data structure.
     */
    protected function applyResolvedValues(array $data, array $resolvedValues): array
    {
        foreach ($resolvedValues as $fieldName => $resolvedValue) {
            $data = $this->setNestedValue($data, $fieldName, $resolvedValue);
        }

        return $data;
    }

    /**
     * Recursively find and update a field by name in a nested array.
     */
    protected function setNestedValue(array $data, string $fieldName, mixed $value): array
    {
        foreach ($data as $key => $item) {
            if ($key === $fieldName) {
                $data[$key] = $value;

                return $data;
            }

            if (is_array($item)) {
                $data[$key] = $this->setNestedValue($item, $fieldName, $value);
            }
        }

        return $data;
    }

    /**
     * Run extraction on a single batch of artifacts.
     *
     * @param  array<int>  $possibleParentIds
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @param  bool  $includeSearchQuery  When true, includes search_query in schema
     * @return array{data: array, parent_id: int|null, confidence: array, page_sources: array, search_query: array}
     */
    protected function runExtractionOnBatch(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $group,
        array $possibleParentIds,
        bool $includeConfidence,
        ?Collection $allArtifacts = null,
        bool $includeSearchQuery = true
    ): array {
        $schemaDefinition = $taskRun->taskDefinition->schemaDefinition;

        // Build the response schema with optional confidence tracking and search query
        $responseSchema = $this->buildExtractionResponseSchema(
            $schemaDefinition,
            $group,
            $possibleParentIds,
            $includeConfidence,
            $includeSearchQuery
        );

        // Run extraction thread on this batch
        $result = $this->runExtractionThread(
            $taskRun,
            $taskProcess,
            $artifacts,
            $responseSchema,
            $possibleParentIds,
            $allArtifacts
        );

        return [
            'data'         => $result['data']         ?? [],
            'parent_id'    => $result['parent_id']    ?? null,
            'confidence'   => $result['confidence']   ?? [],
            'page_sources' => $result['page_sources'] ?? [],
            'search_query' => $result['search_query'] ?? [],
        ];
    }

    /**
     * Check if all identity fields have confidence at or above the threshold.
     *
     * @param  array<string>  $identityFields  List of field names to check
     * @param  array<string, int>  $confidenceScores  Map of field name to confidence score (1-5)
     */
    protected function allFieldsHaveHighConfidence(array $identityFields, array $confidenceScores, int $threshold): bool
    {
        if (empty($identityFields)) {
            // If no identity fields defined, continue processing
            return false;
        }

        $lowConfidenceFields = [];

        foreach ($identityFields as $field) {
            // If field is missing or below threshold, track it
            if (!isset($confidenceScores[$field]) || $confidenceScores[$field] < $threshold) {
                $lowConfidenceFields[] = $field;
            }
        }

        if (!empty($lowConfidenceFields)) {
            static::logDebug('Identity fields below confidence threshold', [
                'fields'    => $lowConfidenceFields,
                'threshold' => $threshold,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Build the response schema for identity extraction.
     *
     * Creates a schema with all metadata at top level:
     * - data: { leaf_key: { fields... } }  -- extracted identity data
     * - page_sources: { "field": 1, ... }  -- page numbers for each field
     * - search_query: [ {...}, ... ]       -- search queries from specific to broad (when includeSearchQuery is true)
     * - parent_id: (optional) When multiple parent options exist
     * - confidence: (optional) When includeConfidence is true
     * - $defs: { pageSource, stringSearch, dateSearch, booleanSearch, numericSearch, integerSearch }
     *
     * @param  array<int>  $possibleParentIds
     * @param  bool  $includeConfidence  When true, adds confidence schema for per-field confidence scores
     * @param  bool  $includeSearchQuery  When true, includes search_query in schema (default true for single objects, false for arrays)
     */
    protected function buildExtractionResponseSchema(
        SchemaDefinition $schemaDefinition,
        array $group,
        array $possibleParentIds,
        bool $includeConfidence = false,
        bool $includeSearchQuery = true
    ): array {
        $fragmentSelector = $group['fragment_selector'] ?? [];
        $identityFields   = $group['identity_fields']   ?? [];
        $objectType       = $group['object_type']       ?? '';

        // Build $defs for the root schema (pageSource + optionally search query types)
        $defs = $this->buildSchemaDefinitions($schemaDefinition->schema, $identityFields, $includeSearchQuery);

        // Get the leaf key and build simplified schema
        $leafKey = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);

        // Build the leaf-level schema (no embedded search_query or __source__)
        $leafSchema = $this->buildLeafSchema($schemaDefinition, $fragmentSelector);

        // Extract ALL extractable field names from the leaf schema for page_sources and confidence
        // This includes identity fields PLUS additional fields like description
        $allExtractableFields = $this->extractFieldNamesFromLeafSchema($leafSchema);

        // Build page_sources schema - uses ALL extractable fields, not just identity fields
        $pageSourceService = app(PageSourceService::class);
        $pageSourcesSchema = $pageSourceService->buildPageSourcesSchema($allExtractableFields);

        $responseSchema = [
            'type'       => 'object',
            'properties' => [
                'data' => [
                    'type'       => 'object',
                    'properties' => [
                        $leafKey => $leafSchema,
                    ],
                ],
                'page_sources' => $pageSourcesSchema,
            ],
            'required' => ['data', 'page_sources'],
            '$defs'    => $defs,
        ];

        // Add search_query schema when requested (skip for array extractions - generated via follow-up request)
        if ($includeSearchQuery) {
            $searchQuerySchema                            = $this->buildSearchQuerySchema($identityFields, $schemaDefinition->schema);
            $responseSchema['properties']['search_query'] = $searchQuerySchema;
            $responseSchema['required'][]                 = 'search_query';
        }

        // Add parent_id field when multiple parents exist
        if (count($possibleParentIds) > 1) {
            $responseSchema['properties']['parent_id'] = [
                'type'        => 'integer',
                'description' => 'The ID of the parent object this data belongs to',
            ];
            $responseSchema['required'][] = 'parent_id';
        }

        // Add confidence schema when requested (for skim mode batched extraction)
        // Uses ALL extractable fields, not just identity fields
        if ($includeConfidence) {
            $responseSchema['properties']['confidence'] = $this->buildConfidenceSchema($allExtractableFields);
            $responseSchema['required'][]               = 'confidence';
        }

        return $responseSchema;
    }

    /**
     * Build the confidence schema for per-field confidence tracking.
     *
     * Returns a schema for { field_name: integer (1-5) } for each identity field.
     *
     * @param  array<string>  $identityFields
     */
    protected function buildConfidenceSchema(array $identityFields): array
    {
        $properties = [];

        foreach ($identityFields as $field) {
            $properties[$field] = [
                'type'        => 'integer',
                'minimum'     => 1,
                'maximum'     => 5,
                'description' => "Confidence score for {$field} (1=very uncertain, 5=highly confident)",
            ];
        }

        // Load description from external file
        $description = trim(file_get_contents(resource_path('prompts/extract-data/confidence-rating-instructions.md')));

        return [
            'type'        => 'object',
            'description' => $description,
            'properties'  => $properties,
            'required'    => $identityFields,
        ];
    }

    /**
     * Build the $defs section for the schema containing pageSource and optionally search query type definitions.
     *
     * @param  array<string>  $identityFields
     * @param  bool  $includeSearchQuery  When true, includes search query type definitions
     */
    protected function buildSchemaDefinitions(?array $schema, array $identityFields, bool $includeSearchQuery = true): array
    {
        $defs = [];

        // Add pageSource definition
        $defs['pageSource'] = app(PageSourceService::class)->getPageSourceDef();

        // Skip search query type definitions when not needed (array extractions use follow-up request)
        if (!$includeSearchQuery) {
            return $defs;
        }

        // Add search query type definitions from yaml
        $searchQueryDefs = $this->getSearchQueryDefs();

        // Only include the defs that are actually used based on field types
        $usedTypes = $this->determineUsedSearchTypes($identityFields, $schema);

        foreach ($usedTypes as $type) {
            if (isset($searchQueryDefs[$type])) {
                $defs[$type] = $searchQueryDefs[$type];
            }
        }

        return $defs;
    }

    /**
     * Determine which search type definitions are needed based on identity field types.
     *
     * @param  array<string>  $identityFields
     * @return array<string>
     */
    protected function determineUsedSearchTypes(array $identityFields, ?array $schema): array
    {
        $usedTypes = [];

        foreach ($identityFields as $field) {
            $fieldType = $this->determineFieldType($field, $schema);

            $searchType = match ($fieldType) {
                'date', 'date-time' => 'dateSearch',
                'boolean'           => 'booleanSearch',
                'integer'           => 'integerSearch',
                'number'            => 'numericSearch',
                default             => 'stringSearch',
            };

            if (!in_array($searchType, $usedTypes, true)) {
                $usedTypes[] = $searchType;
            }
        }

        return $usedTypes;
    }

    /**
     * Build the leaf-level schema without embedded metadata.
     *
     * Navigates through the fragment selector to find the leaf schema.
     * Returns a clean schema without _search_query or __source__ properties.
     */
    protected function buildLeafSchema(
        SchemaDefinition $schemaDefinition,
        array $fragmentSelector
    ): array {
        // Use applyFragmentSelector to get the full schema, then extract leaf level
        $jsonSchemaService = app(JsonSchemaService::class);
        $fullSchema        = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Navigate to the leaf schema
        return $this->extractLeafSchema($fullSchema, $fragmentSelector);
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
     * Extract all scalar field names from a leaf schema.
     *
     * Iterates through the leaf schema properties and extracts field names for scalar types.
     * For array schemas, extracts field names from the items schema.
     *
     * @return array<string> List of extractable field names
     */
    protected function extractFieldNamesFromLeafSchema(array $leafSchema): array
    {
        $fields = [];

        // Handle array type - look at items schema
        if (($leafSchema['type'] ?? '') === 'array') {
            $itemSchema = $leafSchema['items'] ?? [];

            return $this->extractFieldNamesFromLeafSchema($itemSchema);
        }

        // Handle object type - look at properties
        $properties = $leafSchema['properties'] ?? [];

        foreach ($properties as $fieldName => $fieldDef) {
            $fieldType = $fieldDef['type'] ?? 'string';

            // Only include scalar types (not object or array)
            if (!in_array($fieldType, ['object', 'array'], true)) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }

    /**
     * Build the search_query schema as an array of query objects from SPECIFIC to BROAD.
     *
     * The LLM will return MINIMUM 3 search queries ordered from most specific to least specific:
     * - Query 1: Most specific - use exact extracted values
     * - Query 2: Less specific - key identifying terms
     * - Query 3: Broadest - general concept only
     *
     * This enables efficient duplicate detection by checking for exact matches first,
     * then progressively broadening if needed.
     *
     * Field types are inferred from the schema definition and use $ref to search_query.def.yaml:
     * - Strings: Use stringSearch ($ref) - LIKE pattern with % wildcards
     * - Dates: Use dateSearch ($ref) - operator-based comparison (=, <, >, <=, >=, between)
     * - Booleans: Use booleanSearch ($ref) - true/false directly
     * - Numbers: Use numericSearch ($ref) - operator-based comparison
     * - Integers: Use integerSearch ($ref) - operator-based comparison with integer values
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

        // Load description from external file
        $description = trim(file_get_contents(resource_path('prompts/extract-data/search-query-instructions.md')));

        return [
            'type'        => 'array',
            'description' => $description,
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
     * Uses $ref to reference the appropriate search type definition in $defs.
     *
     * Field types map to $defs:
     * - Strings: $ref to stringSearch
     * - Dates/DateTimes: $ref to dateSearch
     * - Booleans: $ref to booleanSearch
     * - Numbers: $ref to numericSearch
     * - Integers: $ref to integerSearch
     */
    protected function buildFieldSearchSchema(string $fieldName, ?array $schema): array
    {
        $fieldType = $this->determineFieldType($fieldName, $schema);

        // Map field type to search definition name
        $defName = match ($fieldType) {
            'date', 'date-time' => 'dateSearch',
            'boolean'           => 'booleanSearch',
            'integer'           => 'integerSearch',
            'number'            => 'numericSearch',
            default             => 'stringSearch',
        };

        return ['$ref' => '#/$defs/' . $defName];
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
     * Run the LLM extraction thread and return parsed results.
     *
     * Response contains all metadata at top level:
     * - data: extracted identity data
     * - page_sources: page numbers for each field
     * - search_query: array of search queries (specific to broad)
     * - confidence: (optional) per-field confidence scores
     * - parent_id: (optional) resolved parent ID
     *
     * @param  array<int>  $possibleParentIds
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @return array{data: array, parent_id: int|null, confidence: array, page_sources: array, search_query: array}
     */
    protected function runExtractionThread(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $responseSchema,
        array $possibleParentIds,
        ?Collection $allArtifacts = null
    ): array {
        $taskDefinition = $taskRun->taskDefinition;

        // Expand artifacts with context pages if configured and enabled
        $config             = $taskDefinition->task_runner_config      ?? [];
        $enableContextPages = $config['enable_context_pages']          ?? false;
        $contextBefore      = $config['classification_context_before'] ?? 0;
        $contextAfter       = $config['classification_context_after']  ?? 0;

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

            static::logDebug('Expanded identity artifacts with context', [
                'target_count'        => $contextService->getTargetCount($artifacts),
                'context_count'       => $contextService->getContextCount($artifacts),
                'total_count'         => $artifacts->count(),
                'adjacency_threshold' => $adjacencyThreshold,
            ]);
        }

        // Build thread with artifacts and optional parent context
        $thread = $this->buildExtractionThread($taskRun, $artifacts, $possibleParentIds);

        // Associate thread with task process for debugging
        $taskProcess->agentThread()->associate($thread)->save();

        // Get timeout from config (default 5 minutes for large extractions)
        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 300;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Create a temporary in-memory SchemaDefinition for the identity extraction response
        $identitySchema = $this->createIdentityExtractionSchema($responseSchema);

        // Enable null values so LLM can return null instead of placeholder strings like "<null>"
        $jsonSchemaService = app(JsonSchemaService::class)->includeNullValues();

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($identitySchema, null, $jsonSchemaService)
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

        // Extract all metadata from top level (new pattern)
        return [
            'data'         => $data['data']         ?? [],
            'parent_id'    => $data['parent_id']    ?? null,
            'confidence'   => $data['confidence']   ?? [],
            'page_sources' => $data['page_sources'] ?? [],
            'search_query' => $data['search_query'] ?? [],
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
        $config         = $taskDefinition->task_runner_config ?? [];

        // Build additional context for parent hierarchy from database
        $parentContext = $this->buildParentContextForMultipleParents($possibleParentIds);

        // Build context page instructions if artifacts have context pages
        $contextInstructions = app(ContextWindowService::class)->buildContextPromptInstructions($artifacts);

        // Build page source instructions if we have page numbers
        $pageSourceInstructions = app(PageSourceService::class)->buildPageSourceInstructions($artifacts);

        // Get user-provided extraction instructions from config
        $extractionInstructions = $config['extraction_instructions'] ?? null;

        $threadBuilder = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Identity Data Extraction')
            ->withArtifacts($artifacts, new ArtifactFilter(
                includeFiles: false,
                includeJson: false,
                includeMeta: false
            ));

        // Add extraction instructions as a system message when available (prominent, near the top)
        if ($extractionInstructions) {
            $threadBuilder->withSystemMessage("## Additional Instructions\n{$extractionInstructions}");
        }

        // Add parent context as a system message when available
        if ($parentContext !== '') {
            $threadBuilder->withSystemMessage($parentContext);
        }

        // Add context page instructions when available
        if ($contextInstructions !== '') {
            $threadBuilder->withSystemMessage($contextInstructions);
        }

        // Add page source instructions when available
        if ($pageSourceInstructions !== '') {
            $threadBuilder->withSystemMessage($pageSourceInstructions);
        }

        return $threadBuilder->build();
    }

    /**
     * Resolve duplicate using DuplicateRecordResolver.
     *
     * The exact match check is performed inside findCandidates(), which returns
     * an exactMatchId when found. If no exact match, falls back to LLM resolution.
     *
     * Returns a ResolutionResult which includes:
     * - Whether a duplicate was found
     * - The existing object ID and object
     * - Updated values to apply to the existing record (when LLM determines better values exist)
     */
    protected function resolveDuplicate(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        array $extractionResult,
        ?int $parentObjectId
    ): ResolutionResult {
        $extractedData = $extractionResult['data']         ?? [];
        $searchQuery   = $extractionResult['search_query'] ?? [];

        if (empty($extractedData)) {
            return new ResolutionResult(
                isDuplicate: false,
                existingObjectId: null,
                existingObject: null,
                explanation: 'No extracted data to compare'
            );
        }

        // The LLM returns an array of queries from most to least restrictive.
        // Normalize to ensure we have the expected array format.
        $searchQueries = $this->normalizeSearchQueries($searchQuery, $extractedData);

        // Resolve the actual root object ID for duplicate scoping.
        // DuplicateRecordResolver filters by root_object_id, which is the level 0 root (e.g., Demand),
        // not the immediate parent. We need to look up the parent's root_object_id.
        $rootObjectId = null;
        if ($parentObjectId) {
            $parentObject = TeamObject::find($parentObjectId);
            if ($parentObject) {
                // If parent has a root_object_id, use that (parent is not the root)
                // If parent has no root_object_id, then parent IS the root
                $rootObjectId = $parentObject->root_object_id ?? $parentObject->id;
            }
        }

        $resolver = app(DuplicateRecordResolver::class);
        $result   = $resolver->findCandidates(
            objectType: $identityGroup['object_type'],
            searchQueries: $searchQueries,
            rootObjectId: $rootObjectId,
            schemaDefinitionId: $taskRun->taskDefinition->schema_definition_id,
            extractedData: $extractedData,
            identityFields: $identityGroup['identity_fields'] ?? [],
            parentObjectId: $parentObjectId
        );

        // If exact match was found during candidate search, return immediately
        // Exact matches do not include updatedValues since they matched exactly
        if ($result->hasExactMatch()) {
            static::logDebug('Exact match found during candidate search', ['object_id' => $result->exactMatchId]);

            return new ResolutionResult(
                isDuplicate: true,
                existingObjectId: $result->exactMatchId,
                existingObject: $result->candidates->first(),
                explanation: 'Exact match found on identity fields',
                confidence: 1.0
            );
        }

        if ($result->candidates->isEmpty()) {
            return new ResolutionResult(
                isDuplicate: false,
                existingObjectId: null,
                existingObject: null,
                explanation: 'No candidates found'
            );
        }

        // LLM Call #2: Resolve which candidate (if any) matches
        // This returns the full ResolutionResult including updatedValues
        $resolution = $resolver->resolveDuplicate(
            extractedData: $extractedData,
            candidates: $result->candidates,
            taskRun: $taskRun,
            taskProcess: $taskProcess
        );

        if ($resolution->hasDuplicate()) {
            static::logDebug('LLM resolution found match', [
                'object_id'          => $resolution->existingObjectId,
                'has_updated_values' => $resolution->hasUpdatedValues(),
            ]);
        }

        return $resolution;
    }

    /**
     * Normalize search queries to array format.
     *
     * The LLM returns search_query as an array of query objects from most to least restrictive.
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
     *
     * Sets up the mapper with rootObject (the level 0 root, e.g., Demand) stored in root_object_id column.
     * Creates a TeamObjectRelationship linking the parent to this child object.
     *
     * @param  string  $relationshipKey  The exact relationship name from the schema (e.g., "providers", "care_summary")
     */
    protected function resolveOrCreateTeamObject(
        TaskRun $taskRun,
        string $objectType,
        array $identificationData,
        string $name,
        ?int $existingId,
        ?int $parentObjectId,
        string $relationshipKey
    ): TeamObject {
        $mapper       = app(JSONSchemaDataToDatabaseMapper::class);
        $parentObject = $parentObjectId ? TeamObject::find($parentObjectId) : null;

        if ($taskRun->taskDefinition->schemaDefinition) {
            $mapper->setSchemaDefinition($taskRun->taskDefinition->schemaDefinition);
        }

        if ($existingId) {
            $teamObject = TeamObject::find($existingId);
            if ($teamObject) {
                // Update existing object with new data
                $mapper->updateTeamObject($teamObject, $identificationData);
                $this->saveIdentityFieldsAsAttributes($mapper, $teamObject, $identificationData);

                // Ensure relationship exists even for existing objects
                if ($parentObject) {
                    $this->ensureParentRelationship($parentObject, $teamObject, $relationshipKey);
                }

                return $teamObject;
            }
        }

        // Set up root object and parent object context if we have a parent
        if ($parentObject) {
            $mapper->setRootObject($this->resolveRootObject($parentObject));
            $mapper->setParentObject($parentObject);
        }

        // Ensure the identificationData has the resolved name to prevent empty string from being
        // filled by updateTeamObject (which is called by createTeamObject)
        $identificationData['name'] = $name;

        $teamObject = $mapper->createTeamObject($objectType, $name, $identificationData);

        $this->saveIdentityFieldsAsAttributes($mapper, $teamObject, $identificationData);

        // Create relationship linking parent to this child
        if ($parentObject) {
            $this->ensureParentRelationship($parentObject, $teamObject, $relationshipKey);
        }

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
     *
     * When borrowing a non-string identity field value, formats it as a human-readable name:
     * - Dates: "2017-10-31" -> "October 31st, 2017"
     * - Booleans: true -> "Yes", false -> "No"
     * - Numbers: 1000.33 -> "1,000.33"
     * - Integers: 50000 -> "50,000"
     */
    protected function resolveObjectName(array $identificationData, array $identityFields): ?string
    {
        // Prefer the literal 'name' field if it exists and has a value
        if (!empty($identificationData['name']) && is_string($identificationData['name'])) {
            return $identificationData['name'];
        }

        // Fall back to first non-empty identity field, formatted for human readability
        foreach ($identityFields as $field) {
            if ($field === 'name') {
                continue; // Already checked above
            }

            $value = $identificationData[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            return $this->formatValueAsName($value);
        }

        return null;
    }

    /**
     * Format a value as a human-readable name string.
     * Used when borrowing identity field values for the name.
     *
     * @return string The formatted name
     */
    protected function formatValueAsName(mixed $value): string
    {
        // Handle boolean
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        // Handle string "true"/"false"
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true' ? 'Yes' : 'No';
        }

        // Handle dates - try to parse and format (check before numeric to handle date strings)
        if (is_string($value) && $this->looksLikeDate($value)) {
            try {
                $date = Carbon::parse($value);

                // Format as "October 31st, 2017"
                return $date->format('F jS, Y');
            } catch (Exception) {
                // If parsing fails, return as-is
                return $value;
            }
        }

        // Handle numeric values
        if (is_numeric($value)) {
            $floatVal = (float)$value;
            // Format with thousands separator, preserve decimals if present
            if (floor($floatVal) == $floatVal) {
                return number_format($floatVal, 0);
            }

            return number_format($floatVal, 2);
        }

        return (string)$value;
    }

    /**
     * Check if a string value looks like a date.
     */
    protected function looksLikeDate(string $value): bool
    {
        // Common date patterns
        $datePatterns = [
            '/^\d{4}-\d{2}-\d{2}$/',           // 2017-10-31 (ISO)
            '/^\d{2}-\d{2}-\d{2}$/',           // 10-31-17
            '/^\d{2}-\d{2}-\d{4}$/',           // 10-31-2017
            '/^\d{2}\/\d{2}\/\d{2}$/',         // 10/31/17
            '/^\d{2}\/\d{2}\/\d{4}$/',         // 10/31/2017
            '/^\d{4}\/\d{2}\/\d{2}$/',         // 2017/10/31
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
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
     * Load the search query type definitions from yaml.
     */
    protected function getSearchQueryDefs(): array
    {
        $path    = app_path('Services/JsonSchema/search_query.def.yaml');
        $content = Yaml::parseFile($path);

        return $content['$defs'] ?? [];
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

        $parentOptions = '';

        foreach ($parentIds as $parentId) {
            $parent = TeamObject::find($parentId);

            if (!$parent) {
                continue;
            }

            $parentOptions .= "Option - ID {$parentId}: {$parent->name} (Type: {$parent->type})\n";

            // Get ancestors from DB
            $ancestors = $this->getAncestorChain($parent);

            if (!empty($ancestors)) {
                $parentOptions .= '  Hierarchy: ';
                $ancestorNames = array_map(fn($a) => "{$a->type}: {$a->name}", $ancestors);
                $parentOptions .= implode(' -> ', $ancestorNames) . " -> {$parent->name}\n";
            }

            $parentOptions .= "\n";
        }

        // Load template from external file
        $template = file_get_contents(resource_path('prompts/extract-data/parent-context-selection.md'));

        return "\n\n" . strtr($template, [
            '{{parent_options}}' => $parentOptions,
        ]);
    }
}
