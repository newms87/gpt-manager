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

        // Get all artifacts from parent output artifact for context expansion
        $allArtifacts = $this->getAllArtifactsFromParent($taskRun);

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
            $possibleParentIds,
            $allArtifacts
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

        // Extract page sources BEFORE cleaning the data (while __source__ fields still exist)
        $pageSourceService = app(PageSourceService::class);
        $pageSources       = is_array($identificationData) ? $pageSourceService->extractPageSources($identificationData) : null;

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

        // Remove __source__ fields from identification data (but we already extracted them above)
        $identificationData = $pageSourceService->removeSourceFields($identificationData);

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

        // Build and attach output artifact(s)
        app(ExtractionArtifactBuilder::class)->buildIdentityArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $identityGroup,
            extractionResult: $itemExtractionResult,
            level: $level,
            matchId: $matchId,
            pageSources: !empty($pageSources) ? $pageSources : null
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

        $createdObjects    = [];
        $artifactBuilder   = app(ExtractionArtifactBuilder::class);
        $pageSourceService = app(PageSourceService::class);

        foreach ($items as $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            // Extract page sources BEFORE cleaning the data (while __source__ fields still exist)
            $pageSources = $pageSourceService->extractPageSources($itemData);

            // Extract embedded _search_query from this item
            $searchQuery = $itemData['_search_query'] ?? $itemData;

            // Remove _search_query from item data (it's not an identity field)
            unset($itemData['_search_query']);

            // Remove __source__ fields from item data (but we already extracted them above)
            $itemData = $pageSourceService->removeSourceFields($itemData);

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

            // Build individual identity artifact(s) for this item
            $artifactBuilder->buildIdentityArtifact(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                teamObject: $teamObject,
                group: $identityGroup,
                extractionResult: $itemExtractionResult,
                level: $level,
                matchId: $matchId,
                pageSources: !empty($pageSources) ? $pageSources : null
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
     *
     * Routes to skim mode or exhaustive mode based on search_mode in process meta.
     *
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     */
    protected function extractIdentityWithSearchQuery(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $group,
        array $possibleParentIds = [],
        ?Collection $allArtifacts = null
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent || !$schemaDefinition) {
            static::logDebug('Missing agent or schema definition for identity extraction');

            return [];
        }

        // Route to appropriate extraction mode based on identity group config
        // The $group parameter already contains the full identity group including search_mode
        $searchMode = $group['search_mode'] ?? 'exhaustive';

        if ($searchMode === 'skim') {
            return $this->extractWithSkimMode(
                $taskRun,
                $taskProcess,
                $group,
                $artifacts,
                $possibleParentIds,
                $allArtifacts
            );
        }

        // Exhaustive mode: process all artifacts in a single request
        $responseSchema = $this->buildExtractionResponseSchema(
            $schemaDefinition,
            $group,
            $possibleParentIds
        );

        return $this->runExtractionThread(
            $taskRun,
            $taskProcess,
            $artifacts,
            $responseSchema,
            $possibleParentIds,
            $allArtifacts
        );
    }

    /**
     * Extract identity data using skim mode.
     * Process artifacts in batches, stopping early when all identity fields have sufficient confidence.
     *
     * @param  array<int>  $possibleParentIds
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @return array{data: array, parent_id: int|null}
     */
    protected function extractWithSkimMode(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $group,
        Collection $artifacts,
        array $possibleParentIds,
        ?Collection $allArtifacts = null
    ): array {
        $config              = $taskRun->taskDefinition->task_runner_config;
        $confidenceThreshold = $config['confidence_threshold'] ?? 3;
        $batchSize           = $config['skim_batch_size']      ?? 5;
        $identityFields      = $group['identity_fields']       ?? [];

        $cumulativeData       = [];
        $cumulativeConfidence = [];
        $resolvedParentId     = null;

        static::logDebug('Starting skim mode identity extraction', [
            'artifact_count'       => $artifacts->count(),
            'confidence_threshold' => $confidenceThreshold,
            'batch_size'           => $batchSize,
            'identity_fields'      => $identityFields,
        ]);

        // Process artifacts in batches
        foreach ($artifacts->chunk($batchSize) as $batchIndex => $batch) {
            static::logDebug("Processing identity extraction batch $batchIndex with " . $batch->count() . ' artifacts');

            $batchResult = $this->runExtractionOnBatch(
                $taskRun,
                $taskProcess,
                $batch,
                $group,
                $possibleParentIds,
                includeConfidence: true,
                allArtifacts: $allArtifacts
            );

            // Merge batch data with cumulative data (later batches override earlier values)
            $batchData      = $batchResult['data'] ?? [];
            $cumulativeData = array_replace_recursive($cumulativeData, $batchData);

            // Use first parent_id found (from first batch that resolves it)
            if ($resolvedParentId === null && !empty($batchResult['parent_id'])) {
                $resolvedParentId = $batchResult['parent_id'];
            }

            // Update confidence scores (take the highest confidence for each field)
            foreach ($batchResult['confidence'] ?? [] as $field => $score) {
                if (!isset($cumulativeConfidence[$field]) || $score > $cumulativeConfidence[$field]) {
                    $cumulativeConfidence[$field] = $score;
                }
            }

            // Check if all identity fields have high enough confidence
            if ($this->allFieldsHaveHighConfidence($identityFields, $cumulativeConfidence, $confidenceThreshold)) {
                $highConfidenceFields = array_filter($cumulativeConfidence, fn($score) => $score >= $confidenceThreshold);
                static::logDebug('Skim mode: stopping early - all identity fields have sufficient confidence', [
                    'batches_processed'      => $batchIndex + 1,
                    'high_confidence_fields' => array_keys($highConfidenceFields),
                    'confidence_scores'      => $cumulativeConfidence,
                ]);
                break;
            }
        }

        return [
            'data'      => $cumulativeData,
            'parent_id' => $resolvedParentId,
        ];
    }

    /**
     * Run extraction on a single batch of artifacts.
     *
     * @param  array<int>  $possibleParentIds
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @return array{data: array, parent_id: int|null, confidence: array}
     */
    protected function runExtractionOnBatch(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        array $group,
        array $possibleParentIds,
        bool $includeConfidence,
        ?Collection $allArtifacts = null
    ): array {
        $schemaDefinition = $taskRun->taskDefinition->schemaDefinition;

        // Build the response schema with optional confidence tracking
        $responseSchema = $this->buildExtractionResponseSchema(
            $schemaDefinition,
            $group,
            $possibleParentIds,
            $includeConfidence
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

        // Extract confidence from result if present
        $data       = $result['data']       ?? [];
        $parentId   = $result['parent_id']  ?? null;
        $confidence = $result['confidence'] ?? [];

        return [
            'data'       => $data,
            'parent_id'  => $parentId,
            'confidence' => $confidence,
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
     * Creates a simplified schema at leaf level with embedded _search_query and __source__ fields:
     * - data: { leaf_key: { fields..., __source__field1: pageSource, _search_query: {...} } }
     * - parent_id: (optional) When multiple parent options exist
     * - confidence: (optional) When includeConfidence is true, adds per-field confidence scores
     * - $defs: { pageSource, stringSearch, dateSearch, booleanSearch, numericSearch, integerSearch }
     *
     * The schema is simplified to only include the leaf-level objects, not the full hierarchy.
     * Each object includes:
     * - __source__ properties for each identity field referencing $defs/pageSource
     * - embedded _search_query property with $ref to search type definitions
     *
     * @param  array<int>  $possibleParentIds
     * @param  bool  $includeConfidence  When true, adds confidence schema for per-field confidence scores
     */
    protected function buildExtractionResponseSchema(
        SchemaDefinition $schemaDefinition,
        array $group,
        array $possibleParentIds,
        bool $includeConfidence = false
    ): array {
        $fragmentSelector = $group['fragment_selector'] ?? [];
        $identityFields   = $group['identity_fields']   ?? [];
        $objectType       = $group['object_type']       ?? '';

        // Build $defs for the root schema (pageSource + search query types)
        $defs = $this->buildSchemaDefinitions($schemaDefinition->schema, $identityFields);

        // Build _search_query schema from identity fields (embedded in each object)
        // Pass schema to enable type-aware search queries for dates, booleans, numbers
        $searchQuerySchema = $this->buildSearchQuerySchema($identityFields, $schemaDefinition->schema);

        // Get the leaf key and build simplified schema
        $leafKey = app(FragmentSelectorService::class)->getLeafKey($fragmentSelector, $objectType);

        // Build the leaf-level schema with embedded _search_query and __source__ properties
        $leafSchema = $this->buildLeafSchemaWithSearchQuery(
            $schemaDefinition,
            $fragmentSelector,
            $searchQuerySchema,
            $leafKey,
            $identityFields
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
            '$defs'    => $defs,
        ];

        // Add parent_id field when multiple parents exist
        if (count($possibleParentIds) > 1) {
            $responseSchema['properties']['parent_id'] = [
                'type'        => 'integer',
                'description' => 'The ID of the parent object this data belongs to',
            ];
            $responseSchema['required'][] = 'parent_id';
        }

        // Add confidence schema when requested (for skim mode batched extraction)
        if ($includeConfidence) {
            $responseSchema['properties']['confidence'] = $this->buildConfidenceSchema($identityFields);
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

        return [
            'type'        => 'object',
            'description' => 'Rate your confidence (1-5) for each extracted identity field. ' .
                             '1=very uncertain/likely incorrect, 2=uncertain/might be wrong, ' .
                             '3=moderately confident/probably correct, 4=confident/very likely correct, ' .
                             '5=highly confident/definitely correct.',
            'properties'  => $properties,
            'required'    => $identityFields,
        ];
    }

    /**
     * Build the $defs section for the schema containing pageSource and search query type definitions.
     *
     * @param  array<string>  $identityFields
     */
    protected function buildSchemaDefinitions(?array $schema, array $identityFields): array
    {
        $defs = [];

        // Add pageSource definition
        $defs['pageSource'] = app(PageSourceService::class)->getPageSourceDef();

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
     * Build the leaf-level schema with embedded _search_query and __source__ properties.
     *
     * Navigates through the fragment selector to find the leaf schema,
     * then adds _search_query and __source__{field} properties to each object.
     *
     * @param  array<string>  $identityFields  Fields that should have corresponding __source__ properties
     */
    protected function buildLeafSchemaWithSearchQuery(
        SchemaDefinition $schemaDefinition,
        array $fragmentSelector,
        array $searchQuerySchema,
        string $leafKey,
        array $identityFields = []
    ): array {
        // Use applyFragmentSelector to get the full schema, then extract leaf level
        $jsonSchemaService = app(JsonSchemaService::class);
        $fullSchema        = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Navigate to the leaf schema
        $leafSchema = $this->extractLeafSchema($fullSchema, $fragmentSelector);

        // Add __source__ properties for identity fields, then add _search_query
        // (handling both object and array types)
        return $this->injectSearchQueryIntoSchema($leafSchema, $searchQuerySchema, $identityFields);
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
     * Inject _search_query and __source__ properties into the schema (handles both object and array types).
     *
     * @param  array<string>  $identityFields  Fields that should have corresponding __source__ properties
     */
    protected function injectSearchQueryIntoSchema(array $schema, array $searchQuerySchema, array $identityFields = []): array
    {
        $type = $schema['type'] ?? 'object';

        if ($type === 'array') {
            // For arrays, add __source__ and _search_query to each item
            $items = $schema['items'] ?? [];
            if (!empty($items)) {
                $itemProperties = $items['properties'] ?? [];

                // Inject __source__ properties for identity fields
                if (!empty($identityFields)) {
                    $itemProperties = app(PageSourceService::class)->injectPageSourceProperties($itemProperties, $identityFields);
                }

                $itemProperties['_search_query'] = $searchQuerySchema;
                $schema['items']['properties']   = $itemProperties;
            }
        } else {
            // For objects, add __source__ and _search_query directly
            $properties = $schema['properties'] ?? [];

            // Inject __source__ properties for identity fields
            if (!empty($identityFields)) {
                $properties = app(PageSourceService::class)->injectPageSourceProperties($properties, $identityFields);
            }

            $properties['_search_query'] = $searchQuerySchema;
            $schema['properties']        = $properties;
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

        return [
            'type'        => 'array',
            'description' => 'MINIMUM 3 search queries ordered from MOST SPECIFIC to LEAST SPECIFIC (exact match first, then progressively broader). Purpose: Find existing records efficiently - we check for exact matches first, then broaden if needed. Query 1: Most specific - use exact extracted values. Query 2: Less specific - key identifying terms. Query 3: Broadest - general concept only. Example for "Dr. John Smith": [{name: ["Dr.", "John", "Smith"]}, {name: ["John", "Smith"]}, {name: ["Smith"]}].',
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
     * The response contains data with embedded _search_query in each object.
     * NOTE: search_query is no longer a top-level field - it's now embedded as _search_query in each object.
     *
     * When confidence tracking is enabled (skim mode), the response also includes a confidence
     * object with per-field confidence scores (1-5).
     *
     * @param  array<int>  $possibleParentIds
     * @param  Collection|null  $allArtifacts  All artifacts for context expansion (optional)
     * @return array{data: array, parent_id: int|null, confidence: array}
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
            'data'       => $data['data']       ?? [],
            'parent_id'  => $data['parent_id']  ?? null,
            'confidence' => $data['confidence'] ?? [],
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

    /**
     * Get all artifacts from the parent output artifact.
     *
     * These are all page artifacts (children of the parent output artifact),
     * regardless of classification. Used for context page expansion.
     *
     * @return Collection<\App\Models\Task\Artifact>
     */
    protected function getAllArtifactsFromParent(TaskRun $taskRun): Collection
    {
        $parentArtifact = app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            return collect();
        }

        return $parentArtifact->children()->orderBy('position')->get();
    }
}
