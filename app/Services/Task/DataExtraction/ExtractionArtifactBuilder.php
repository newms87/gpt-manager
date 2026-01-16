<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;
use App\Traits\TeamObjectRelationshipHelper;
use Illuminate\Support\Str;

/**
 * Handles artifact creation and parent-child linking for extraction operations.
 * Eliminates duplicated artifact creation code from ExtractDataTaskRunner.
 */
class ExtractionArtifactBuilder
{
    use HasDebugLogging;
    use TeamObjectRelationshipHelper;

    /**
     * Build and attach identity extraction artifact(s).
     * When page sources are provided, creates per-page artifacts linked to their source pages.
     *
     * @param  TeamObject|null  $parentObject  The immediate parent object (from extraction context).
     *                                         When provided, this is used directly instead of querying
     *                                         relationships from the database. This ensures correct
     *                                         parent linkage when a TeamObject has multiple parent
     *                                         relationships from different extraction runs.
     * @return array<Artifact> Array of created artifacts
     */
    public function buildIdentityArtifact(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        TeamObject $teamObject,
        array $group,
        array $extractionResult,
        int $level,
        ?int $matchId,
        ?array $pageSources = null,
        ?TeamObject $parentObject = null
    ): array {
        $extractedData = $extractionResult['data'] ?? [];

        // Build ancestor chain from the explicitly provided parent object, or fall back to DB lookup
        // Using the explicit parent ensures correct linkage when TeamObjects have multiple parent
        // relationships from different extraction runs
        $ancestors = $parentObject
            ? $this->buildAncestorChainFromParent($parentObject)
            : $this->getAncestorChain($teamObject);

        // Get immediate parent for artifact metadata (last ancestor in chain)
        $immediateParent = !empty($ancestors) ? end($ancestors) : null;

        // Get the actual relationship key from the fragment_selector (schema is source of truth)
        $fragmentSelectorService = app(FragmentSelectorService::class);
        $fragmentSelector        = $group['fragment_selector'] ?? [];
        $objectType              = $group['object_type']       ?? '';
        $relationshipKey         = $fragmentSelectorService->getLeafKey($fragmentSelector, $objectType);
        $isArrayType             = $fragmentSelectorService->isLeafArrayType($group);

        // If no page sources, create single artifact from all data
        if (empty($pageSources)) {
            $artifact = $this->createArtifact(
                taskRun: $taskRun,
                name: "Identity: {$group['object_type']} - " . ($teamObject->name ?? 'Unknown'),
                jsonContent: $this->buildHierarchicalJsonFromAncestors(
                    teamObject: $teamObject,
                    extractedData: $extractedData,
                    group: $group,
                    ancestors: $ancestors
                ),
                meta: [
                    'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
                    'search_query'     => $extractionResult['search_query'] ?? null,
                    'was_existing'     => $matchId !== null,
                    'match_id'         => $matchId,
                    'task_process_id'  => $taskProcess->id,
                    'level'            => $level,
                    'identity_group'   => $group['name'] ?? $group['object_type'],
                    'parent_id'        => $immediateParent?->id,
                    'parent_type'      => $immediateParent?->type,
                    'relationship_key' => $relationshipKey,
                    'is_array_type'    => $isArrayType,
                ]
            );

            $this->attachToProcessAndLinkParent($artifact, $taskProcess);

            static::logDebug('Built identity extraction artifact (single)', [
                'artifact_id'  => $artifact->id,
                'object_type'  => $group['object_type'],
                'was_existing' => $matchId !== null,
            ]);

            return [$artifact];
        }

        // Use PageSourceService to split data by page
        $pageSourceService = app(PageSourceService::class);
        $dataByPage        = $pageSourceService->splitDataByPage($extractedData, $pageSources);

        $artifacts      = [];
        $inputArtifacts = $taskProcess->inputArtifacts;

        foreach ($dataByPage as $pageNumber => $pageData) {
            // Find the artifact for this page
            $pageArtifact = $pageSourceService->findArtifactByPage($inputArtifacts, $pageNumber);

            if (!$pageArtifact) {
                static::logDebug('No page artifact found for page', [
                    'page_number' => $pageNumber,
                    'object_type' => $group['object_type'],
                ]);

                continue;
            }

            // Create artifact with only this page's data
            $artifact = $this->createArtifact(
                taskRun: $taskRun,
                name: "Identity: {$group['object_type']} - Page {$pageNumber}",
                jsonContent: $this->buildHierarchicalJsonFromAncestors(
                    teamObject: $teamObject,
                    extractedData: $pageData,
                    group: $group,
                    ancestors: $ancestors
                ),
                meta: [
                    'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
                    'page_number'      => $pageNumber,
                    'source_fields'    => array_keys($pageData),
                    'search_query'     => $extractionResult['search_query'] ?? null,
                    'was_existing'     => $matchId !== null,
                    'match_id'         => $matchId,
                    'task_process_id'  => $taskProcess->id,
                    'level'            => $level,
                    'identity_group'   => $group['name'] ?? $group['object_type'],
                    'parent_id'        => $immediateParent?->id,
                    'parent_type'      => $immediateParent?->type,
                    'relationship_key' => $relationshipKey,
                    'is_array_type'    => $isArrayType,
                ]
            );

            // Attach to specific page artifact
            $this->attachToPageArtifact($artifact, $taskProcess, $pageArtifact);

            $artifacts[] = $artifact;

            static::logDebug('Built identity extraction artifact (per-page)', [
                'artifact_id'        => $artifact->id,
                'page_number'        => $pageNumber,
                'source_fields'      => array_keys($pageData),
                'parent_artifact_id' => $pageArtifact->id,
            ]);
        }

        // If no artifacts were created (all pages missing), fall back to original behavior
        if (empty($artifacts)) {
            static::logDebug('No per-page artifacts created, falling back to single artifact', [
                'object_type'  => $group['object_type'],
                'page_sources' => $pageSources,
            ]);

            $artifact = $this->createArtifact(
                taskRun: $taskRun,
                name: "Identity: {$group['object_type']} - " . ($teamObject->name ?? 'Unknown'),
                jsonContent: $this->buildHierarchicalJsonFromAncestors(
                    teamObject: $teamObject,
                    extractedData: $extractedData,
                    group: $group,
                    ancestors: $ancestors
                ),
                meta: [
                    'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
                    'search_query'     => $extractionResult['search_query'] ?? null,
                    'was_existing'     => $matchId !== null,
                    'match_id'         => $matchId,
                    'task_process_id'  => $taskProcess->id,
                    'level'            => $level,
                    'identity_group'   => $group['name'] ?? $group['object_type'],
                    'parent_id'        => $immediateParent?->id,
                    'parent_type'      => $immediateParent?->type,
                    'relationship_key' => $relationshipKey,
                    'is_array_type'    => $isArrayType,
                ]
            );

            $this->attachToProcessAndLinkParent($artifact, $taskProcess);

            return [$artifact];
        }

        return $artifacts;
    }

    /**
     * Check if the leaf level in the fragment_selector is an array type.
     */
    public function isLeafArrayType(array $group): bool
    {
        return app(FragmentSelectorService::class)->isLeafArrayType($group);
    }

    /**
     * Build and attach remaining extraction artifact(s).
     * When page sources are provided, creates per-page artifacts linked to their source pages.
     *
     * @param  TeamObject|null  $parentObject  The immediate parent object (from extraction context).
     *                                         When provided, this is used directly instead of querying
     *                                         relationships from the database. This ensures correct
     *                                         parent linkage when a TeamObject has multiple parent
     *                                         relationships from different extraction runs.
     * @return array<Artifact> Array of created artifacts
     */
    public function buildRemainingArtifact(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        TeamObject $teamObject,
        array $group,
        array $extractedData,
        int $level,
        string $searchMode,
        ?array $pageSources = null,
        ?TeamObject $parentObject = null
    ): array {
        // Build ancestor chain from the explicitly provided parent object, or fall back to DB lookup
        // Using the explicit parent ensures correct linkage when TeamObjects have multiple parent
        // relationships from different extraction runs
        $ancestors = $parentObject
            ? $this->buildAncestorChainFromParent($parentObject)
            : $this->getAncestorChain($teamObject);

        // Get immediate parent for artifact metadata (last ancestor in chain)
        $immediateParent = !empty($ancestors) ? end($ancestors) : null;

        // Get the actual relationship key from the fragment_selector (schema is source of truth)
        $fragmentSelectorService = app(FragmentSelectorService::class);
        $fragmentSelector        = $group['fragment_selector'] ?? [];
        $objectType              = $group['object_type']       ?? '';
        $relationshipKey         = $fragmentSelectorService->getLeafKey($fragmentSelector, $objectType);
        $isArrayType             = $fragmentSelectorService->isLeafArrayType($group);

        // If no page sources, create single artifact from all data
        if (empty($pageSources)) {
            $artifact = $this->createArtifact(
                taskRun: $taskRun,
                name: "Remaining: {$group['name']} - " . ($teamObject->name ?? 'Unknown'),
                jsonContent: $this->buildHierarchicalJsonFromAncestors(
                    teamObject: $teamObject,
                    extractedData: $extractedData,
                    group: $group,
                    ancestors: $ancestors
                ),
                meta: [
                    'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
                    'extraction_mode'  => $searchMode,
                    'task_process_id'  => $taskProcess->id,
                    'level'            => $level,
                    'extraction_group' => $group['name'] ?? $group['object_type'],
                    'parent_id'        => $immediateParent?->id,
                    'parent_type'      => $immediateParent?->type,
                    'relationship_key' => $relationshipKey,
                    'is_array_type'    => $isArrayType,
                ]
            );

            $this->attachToProcessAndLinkParent($artifact, $taskProcess);

            static::logDebug('Built remaining extraction artifact (single)', [
                'artifact_id' => $artifact->id,
                'group_name'  => $group['name'] ?? 'Unknown',
                'level'       => $level,
            ]);

            return [$artifact];
        }

        // Use PageSourceService to split data by page
        $pageSourceService = app(PageSourceService::class);
        $dataByPage        = $pageSourceService->splitDataByPage($extractedData, $pageSources);

        $artifacts      = [];
        $inputArtifacts = $taskProcess->inputArtifacts;

        foreach ($dataByPage as $pageNumber => $pageData) {
            // Find the artifact for this page
            $pageArtifact = $pageSourceService->findArtifactByPage($inputArtifacts, $pageNumber);

            if (!$pageArtifact) {
                static::logDebug('No page artifact found for page', [
                    'page_number' => $pageNumber,
                    'group_name'  => $group['name'] ?? 'Unknown',
                ]);

                continue;
            }

            // Create artifact with only this page's data
            $artifact = $this->createArtifact(
                taskRun: $taskRun,
                name: "Remaining: {$group['name']} - Page {$pageNumber}",
                jsonContent: $this->buildHierarchicalJsonFromAncestors(
                    teamObject: $teamObject,
                    extractedData: $pageData,
                    group: $group,
                    ancestors: $ancestors
                ),
                meta: [
                    'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
                    'page_number'      => $pageNumber,
                    'source_fields'    => array_keys($pageData),
                    'extraction_mode'  => $searchMode,
                    'task_process_id'  => $taskProcess->id,
                    'level'            => $level,
                    'extraction_group' => $group['name'] ?? $group['object_type'],
                    'parent_id'        => $immediateParent?->id,
                    'parent_type'      => $immediateParent?->type,
                    'relationship_key' => $relationshipKey,
                    'is_array_type'    => $isArrayType,
                ]
            );

            // Attach to specific page artifact
            $this->attachToPageArtifact($artifact, $taskProcess, $pageArtifact);

            $artifacts[] = $artifact;

            static::logDebug('Built remaining extraction artifact (per-page)', [
                'artifact_id'        => $artifact->id,
                'page_number'        => $pageNumber,
                'source_fields'      => array_keys($pageData),
                'parent_artifact_id' => $pageArtifact->id,
            ]);
        }

        // If no artifacts were created (all pages missing), fall back to original behavior
        if (empty($artifacts)) {
            static::logDebug('No per-page artifacts created, falling back to single artifact', [
                'group_name'   => $group['name'] ?? 'Unknown',
                'page_sources' => $pageSources,
            ]);

            $artifact = $this->createArtifact(
                taskRun: $taskRun,
                name: "Remaining: {$group['name']} - " . ($teamObject->name ?? 'Unknown'),
                jsonContent: $this->buildHierarchicalJsonFromAncestors(
                    teamObject: $teamObject,
                    extractedData: $extractedData,
                    group: $group,
                    ancestors: $ancestors
                ),
                meta: [
                    'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
                    'extraction_mode'  => $searchMode,
                    'task_process_id'  => $taskProcess->id,
                    'level'            => $level,
                    'extraction_group' => $group['name'] ?? $group['object_type'],
                    'parent_id'        => $immediateParent?->id,
                    'parent_type'      => $immediateParent?->type,
                    'relationship_key' => $relationshipKey,
                    'is_array_type'    => $isArrayType,
                ]
            );

            $this->attachToProcessAndLinkParent($artifact, $taskProcess);

            return [$artifact];
        }

        return $artifacts;
    }

    /**
     * Unwrap extracted data while preserving array at the leaf level.
     * Used by RemainingExtractionService for array extraction.
     */
    public function unwrapExtractedDataPreservingLeaf(array $extractedData, array $fragmentSelector): array
    {
        return app(FragmentSelectorService::class)->unwrapData($extractedData, $fragmentSelector, preserveLeafArray: true);
    }

    /**
     * Build hierarchical JSON structure for artifact content.
     * Uses pre-computed ancestor chain to avoid duplicate DB queries.
     * Root objects (no ancestors): flat structure
     * Child objects: nested under full ancestor hierarchy
     *
     * @param  array<TeamObject>  $ancestors  Pre-computed ancestor chain [root, ..., immediate parent]
     */
    protected function buildHierarchicalJsonFromAncestors(
        TeamObject $teamObject,
        array $extractedData,
        array $group,
        array $ancestors
    ): array {
        $objectType       = $group['object_type']       ?? '';
        $fragmentSelector = $group['fragment_selector'] ?? [];

        // Unwrap extracted data recursively through fragment_selector hierarchy
        $extractedData = app(FragmentSelectorService::class)->unwrapData($extractedData, $fragmentSelector);

        $currentData = array_merge(
            ['id' => $teamObject->id, 'type' => $objectType],
            $extractedData
        );

        // If no ancestors, this is a root object - return flat structure
        if (empty($ancestors)) {
            return $currentData;
        }

        // Build nested structure under ancestors
        return $this->nestDataUnderAncestors($currentData, $ancestors, $group);
    }

    /**
     * Nest the current object's data under its ancestors from root down.
     *
     * @param  array  $currentData  The current object data (id, type, extracted fields)
     * @param  array  $ancestors  Array of TeamObject ancestors [root, ..., immediate parent]
     * @param  array  $group  The extraction group with fragment_selector
     */
    protected function nestDataUnderAncestors(array $currentData, array $ancestors, array $group): array
    {
        $fragmentSelector        = $group['fragment_selector'] ?? [];
        $fragmentSelectorService = app(FragmentSelectorService::class);

        // Get the leaf schema key using the service
        $schemaKey   = $fragmentSelectorService->getLeafKey($fragmentSelector, $group['object_type'] ?? null);
        $currentType = $currentData['type'] ?? '';

        // Start with current data
        $result = $currentData;

        // Work backwards from immediate parent to root
        // For each ancestor, wrap the current result
        $ancestorCount = count($ancestors);

        for ($i = $ancestorCount - 1; $i >= 0; $i--) {
            $ancestor = $ancestors[$i];

            // Determine the nesting key for this level
            if ($i === $ancestorCount - 1) {
                // Immediate parent - use schema key from fragment_selector or snake_case of current type
                $nestKey = $schemaKey ?: Str::snake($currentType);
            } else {
                // Higher parent - need the type of the next level down (which is now wrapped in $result)
                // This is the type of ancestors[$i + 1]
                $nextAncestor = $ancestors[$i + 1];
                $nestKey      = Str::snake($nextAncestor->type);
            }

            // Determine if this should be an array or object based on fragment_selector
            $keyIndex = $i;
            $isArray  = $fragmentSelectorService->isArrayTypeAtLevel($fragmentSelector, $keyIndex);

            $result = [
                'id'     => $ancestor->id,
                'type'   => $ancestor->type,
                $nestKey => $isArray ? [$result] : $result,
            ];
        }

        return $result;
    }

    /**
     * Create the artifact with common fields.
     */
    protected function createArtifact(
        TaskRun $taskRun,
        string $name,
        array $jsonContent,
        array $meta
    ): Artifact {
        return Artifact::create([
            'name'               => $name,
            'task_definition_id' => $taskRun->task_definition_id,
            'task_run_id'        => $taskRun->id,
            'team_id'            => $taskRun->taskDefinition->team_id,
            'json_content'       => $jsonContent,
            'meta'               => $meta,
        ]);
    }

    /**
     * Build ancestor chain starting from an explicitly provided parent object.
     *
     * When the parent is known from the extraction context (passed as parentObjectId),
     * we build the ancestor chain by getting the parent's ancestors and appending the parent itself.
     * This ensures correct parent linkage regardless of what relationships exist in the database.
     *
     * @param  TeamObject  $parentObject  The immediate parent object
     * @return array<TeamObject> Ancestor chain [root, ..., immediate parent]
     */
    protected function buildAncestorChainFromParent(TeamObject $parentObject): array
    {
        // Get the parent's ancestor chain from the database
        $parentAncestors = $this->getAncestorChain($parentObject);

        // Append the parent itself to form the complete chain for the current object
        return [...$parentAncestors, $parentObject];
    }

    /**
     * Attach artifact to process outputs and link as child of input artifact.
     */
    protected function attachToProcessAndLinkParent(Artifact $artifact, TaskProcess $taskProcess): void
    {
        // Attach to task process output artifacts
        $taskProcess->outputArtifacts()->attach($artifact->id);
        $taskProcess->updateRelationCounter('outputArtifacts');

        // Link as child of input artifact (page artifact)
        $inputArtifact = $taskProcess->inputArtifacts()->first();

        if (!$inputArtifact) {
            return;
        }

        $artifact->parent_artifact_id = $inputArtifact->id;
        $artifact->save();

        // Update parent artifact's child count
        $inputArtifact->updateRelationCounter('children');
    }

    /**
     * Attach artifact to process outputs and link as child of specific page artifact.
     */
    protected function attachToPageArtifact(Artifact $artifact, TaskProcess $taskProcess, Artifact $pageArtifact): void
    {
        // Attach to task process output artifacts
        $taskProcess->outputArtifacts()->attach($artifact->id);
        $taskProcess->updateRelationCounter('outputArtifacts');

        // Link as child of the specific page artifact
        $artifact->parent_artifact_id = $pageArtifact->id;
        $artifact->save();

        // Update page artifact's child count
        $pageArtifact->updateRelationCounter('children');
    }
}
