<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Traits\HasDebugLogging;

/**
 * Handles the remaining field extraction workflow for extraction groups.
 * Orchestrates loading TeamObjects, getting classified artifacts,
 * routing to skim or exhaustive extraction, and building output artifacts.
 */
class RemainingExtractionService
{
    use HasDebugLogging;

    /**
     * Execute remaining field extraction for a task process.
     *
     * Returns the extracted data array, or empty array if extraction failed.
     */
    public function execute(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $extractionGroup,
        int $level,
        int $teamObjectId,
        string $searchMode = 'exhaustive'
    ): array {
        static::logDebug('Starting remaining extraction', [
            'team_object_id' => $teamObjectId,
            'group_name'     => $extractionGroup['name'] ?? $extractionGroup['object_type'],
            'search_mode'    => $searchMode,
            'level'          => $level,
        ]);

        // Load the TeamObject to update
        $teamObject = TeamObject::find($teamObjectId);

        if (!$teamObject) {
            static::logDebug('TeamObject not found for remaining extraction', [
                'team_object_id' => $teamObjectId,
            ]);

            return [];
        }

        // Use input artifacts already attached to the process (filtered by classification during process creation)
        $artifacts = $taskProcess->inputArtifacts;

        if ($artifacts->isEmpty()) {
            throw new \Exception(
                "Extract Remaining process {$taskProcess->id} has no input artifacts. " .
                'This is a bug - processes should not be created without artifacts.'
            );
        }

        // Get all artifacts from parent output artifact for context expansion
        $allArtifacts = $this->getAllArtifactsFromParent($taskRun);

        $groupExtractionService = app(GroupExtractionService::class);

        // Route to appropriate extraction mode
        $extractionResult = match ($searchMode) {
            'skim' => $groupExtractionService->extractWithSkimMode(
                $taskRun,
                $taskProcess,
                $extractionGroup,
                $artifacts,
                $teamObject,
                $allArtifacts
            ),
            default => $groupExtractionService->extractExhaustive(
                $taskRun,
                $taskProcess,
                $extractionGroup,
                $artifacts,
                $teamObject,
                $allArtifacts
            ),
        };

        $extractedData = $extractionResult['data']         ?? [];
        $pageSources   = $extractionResult['page_sources'] ?? [];

        if (empty($extractedData)) {
            static::logDebug('Extraction returned no data');

            return [];
        }

        // Check if this is an array-type extraction (e.g., complaints, treatments)
        $artifactBuilder = app(ExtractionArtifactBuilder::class);
        if ($artifactBuilder->isLeafArrayType($extractionGroup)) {
            return $this->executeArrayExtraction(
                taskRun: $taskRun,
                taskProcess: $taskProcess,
                extractionGroup: $extractionGroup,
                level: $level,
                parentObject: $teamObject,
                extractedData: $extractedData,
                searchMode: $searchMode,
                pageSources: !empty($pageSources) ? $pageSources : null
            );
        }

        // Continue with existing single-object flow
        // Update TeamObject with extracted data
        $groupExtractionService->updateTeamObjectWithExtractedData(
            $taskRun,
            $teamObject,
            $extractedData,
            $extractionGroup
        );

        // Build and attach output artifact(s)
        $artifactBuilder->buildRemainingArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $extractionGroup,
            extractedData: $extractedData,
            level: $level,
            searchMode: $searchMode,
            pageSources: !empty($pageSources) ? $pageSources : null
        );

        static::logDebug('Remaining extraction completed', [
            'team_object_id' => $teamObject->id,
            'fields_count'   => count($extractedData),
        ]);

        return $extractedData;
    }

    /**
     * Execute array extraction - creates multiple TeamObjects from array data.
     */
    protected function executeArrayExtraction(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $extractionGroup,
        int $level,
        TeamObject $parentObject,
        array $extractedData,
        string $searchMode,
        ?array $pageSources = null
    ): array {
        $artifactBuilder  = app(ExtractionArtifactBuilder::class);
        $fragmentSelector = $extractionGroup['fragment_selector'] ?? [];

        // Unwrap to get array of items (preserving the array at leaf level)
        $items = $artifactBuilder->unwrapExtractedDataPreservingLeaf($extractedData, $fragmentSelector);

        if (!is_array($items) || empty($items) || !isset($items[0])) {
            static::logDebug('No array items found in extraction result');

            return [];
        }

        $createdObjects = [];
        $objectType     = $extractionGroup['object_type'];

        static::logDebug('Processing array extraction', [
            'item_count'  => count($items),
            'object_type' => $objectType,
            'parent_id'   => $parentObject->id,
        ]);

        foreach ($items as $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            // Duplicate resolution scoped to parent
            $existingId = $this->findExistingChildObject($parentObject, $objectType, $itemData);

            // Create or update TeamObject
            $teamObject = $this->createOrUpdateTeamObject(
                $taskRun,
                $objectType,
                $itemData,
                $existingId,
                $parentObject->id
            );

            $createdObjects[] = $teamObject;

            static::logDebug('Processed array item', [
                'object_id'   => $teamObject->id,
                'object_name' => $teamObject->name,
                'was_update'  => $existingId !== null,
            ]);
        }

        // Store ALL resolved objects in input artifacts
        $objectIds = array_map(fn($obj) => $obj->id, $createdObjects);
        app(ResolvedObjectsService::class)->storeMultipleInProcessArtifacts($taskProcess, $objectType, $objectIds);

        // Build single artifact(s) containing all extracted data
        $artifactBuilder->buildRemainingArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $parentObject,
            group: $extractionGroup,
            extractedData: $extractedData,
            level: $level,
            searchMode: $searchMode,
            pageSources: $pageSources
        );

        static::logDebug('Array extraction completed', [
            'created_count' => count($createdObjects),
        ]);

        return $extractedData;
    }

    /**
     * Find an existing child object of the given type under the parent.
     * Used for duplicate resolution scoped to parent.
     */
    protected function findExistingChildObject(
        TeamObject $parentObject,
        string $objectType,
        array $itemData
    ): ?int {
        // Query for existing objects of this type that have this parent
        $candidates = TeamObject::where('type', $objectType)
            ->whereHas('relatedToMe', fn($q) => $q->where('team_object_id', $parentObject->id))
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Quick match check on name (exact match)
        $name = $itemData['name'] ?? null;
        if ($name) {
            $exactMatch = $candidates->first(fn($c) => $c->name === $name);
            if ($exactMatch) {
                static::logDebug('Found exact name match for duplicate resolution', [
                    'name'      => $name,
                    'object_id' => $exactMatch->id,
                ]);

                return $exactMatch->id;
            }
        }

        return null; // No match found, will create new
    }

    /**
     * Create or update a TeamObject from extracted item data.
     */
    protected function createOrUpdateTeamObject(
        TaskRun $taskRun,
        string $objectType,
        array $itemData,
        ?int $existingId,
        int $parentObjectId
    ): TeamObject {
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);

        if ($taskRun->taskDefinition->schemaDefinition) {
            $mapper->setSchemaDefinition($taskRun->taskDefinition->schemaDefinition);
        }

        $parentObject = TeamObject::find($parentObjectId);
        if ($parentObject) {
            $mapper->setRootObject($parentObject);
        }

        if ($existingId) {
            $teamObject = TeamObject::find($existingId);
            if ($teamObject) {
                $mapper->updateTeamObject($teamObject, $itemData);

                return $teamObject;
            }
        }

        // Create new TeamObject
        $name = $itemData['name'] ?? $objectType;

        return $mapper->createTeamObject($objectType, $name, $itemData);
    }

    /**
     * Get all artifacts from the parent output artifact.
     *
     * These are all page artifacts (children of the parent output artifact),
     * regardless of classification. Used for context page expansion.
     *
     * @return \Illuminate\Support\Collection<\App\Models\Task\Artifact>
     */
    protected function getAllArtifactsFromParent(TaskRun $taskRun): \Illuminate\Support\Collection
    {
        $parentArtifact = app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            return collect();
        }

        return $parentArtifact->children()->orderBy('position')->get();
    }
}
