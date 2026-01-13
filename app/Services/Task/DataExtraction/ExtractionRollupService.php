<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Traits\HasDebugLogging;
use Carbon\Carbon;

/**
 * Collects and rolls up all extracted data from child artifacts into a single
 * structured JSON format stored in the parent output artifact's json_content.
 */
class ExtractionRollupService
{
    use HasDebugLogging;

    /**
     * Roll up all extracted data from a TaskRun into its output artifact.
     * Collects data from resolved objects and stores in the parent artifact's json_content.
     */
    public function rollupTaskRunData(TaskRun $taskRun): void
    {
        static::logDebug('Starting extraction rollup', ['task_run_id' => $taskRun->id]);

        $parentArtifact = $this->getOutputArtifact($taskRun);

        if (!$parentArtifact) {
            static::logDebug('No output artifact found for rollup');

            return;
        }

        // Check if already rolled up (json_content exists and is not empty)
        if (!empty($parentArtifact->json_content)) {
            static::logDebug('Rollup already complete', ['artifact_id' => $parentArtifact->id]);

            return;
        }

        $extractedData    = $this->collectExtractedData($taskRun);
        $rollupStructure  = $this->buildRollupStructure($extractedData);

        $parentArtifact->json_content = $rollupStructure;
        $parentArtifact->save();

        static::logDebug('Completed extraction rollup', [
            'artifact_id'   => $parentArtifact->id,
            'total_objects' => $rollupStructure['summary']['total_objects'] ?? 0,
        ]);
    }

    /**
     * Get the top-level output artifact for a TaskRun.
     */
    public function getOutputArtifact(TaskRun $taskRun): ?Artifact
    {
        return app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);
    }

    /**
     * Collect all extracted data from resolved objects.
     * Returns array of TeamObjects with their attributes loaded.
     *
     * @return array<string, array<int, TeamObject>> Grouped by object type
     */
    public function collectExtractedData(TaskRun $taskRun): array
    {
        $resolvedObjects = app(ExtractionProcessOrchestrator::class)->getResolvedObjectIds($taskRun);

        static::logDebug('Collecting extracted data', ['resolved_objects' => $resolvedObjects]);

        if (empty($resolvedObjects)) {
            return [];
        }

        // Collect all object IDs from the resolved objects structure
        // Structure: ['ObjectType' => [level => [id1, id2, ...]]]
        $objectsByType = [];

        foreach ($resolvedObjects as $objectType => $levelData) {
            $allIds = [];

            foreach ($levelData as $level => $ids) {
                $allIds = array_merge($allIds, $ids);
            }

            if (!empty($allIds)) {
                // Load TeamObjects with their attributes
                $objects = TeamObject::whereIn('id', $allIds)
                    ->with('attributes')
                    ->get();

                $objectsByType[$objectType] = $objects->all();
            }
        }

        static::logDebug('Collected extracted data', [
            'types_count' => count($objectsByType),
            'objects'     => array_map(fn($objs) => count($objs), $objectsByType),
        ]);

        return $objectsByType;
    }

    /**
     * Build the rollup structure from extracted data.
     * Organizes data by object type with hierarchical structure.
     */
    public function buildRollupStructure(array $extractedData): array
    {
        $objectTypes = [];
        $summary     = [
            'total_objects' => 0,
            'by_type'       => [],
        ];

        foreach ($extractedData as $objectType => $objects) {
            $count                        = count($objects);
            $summary['total_objects']     += $count;
            $summary['by_type'][$objectType] = $count;

            $objectTypes[$objectType] = [
                'count'   => $count,
                'objects' => array_map(fn($obj) => $this->formatTeamObject($obj), $objects),
            ];
        }

        // Build hierarchical relationships
        $objectTypes = $this->buildHierarchy($objectTypes, $extractedData);

        return [
            'extracted_at' => Carbon::now()->toIso8601String(),
            'object_types' => $objectTypes,
            'summary'      => $summary,
        ];
    }

    /**
     * Format a TeamObject for the rollup output.
     */
    protected function formatTeamObject(TeamObject $teamObject): array
    {
        $attributes = [];

        foreach ($teamObject->attributes as $attribute) {
            $value                        = $attribute->text_value ?? $attribute->json_value;
            $attributes[$attribute->name] = $value;
        }

        return [
            'id'         => $teamObject->id,
            'name'       => $teamObject->name,
            'date'       => $teamObject->date?->format('Y-m-d'),
            'attributes' => $attributes,
            'children'   => [], // Will be populated by buildHierarchy
        ];
    }

    /**
     * Build hierarchical relationships between objects.
     * Root objects (no root_object_id) are at the top level,
     * child objects are nested under their parents.
     */
    protected function buildHierarchy(array $objectTypes, array $extractedData): array
    {
        // Build a map of all objects by ID for quick lookup
        $objectMap = [];
        foreach ($extractedData as $objects) {
            foreach ($objects as $obj) {
                $objectMap[$obj->id] = $obj;
            }
        }

        // Identify root vs child objects
        foreach ($objectTypes as $objectType => &$typeData) {
            $rootObjects  = [];
            $childObjects = [];

            foreach ($typeData['objects'] as $index => $formattedObject) {
                $originalObject = $extractedData[$objectType][$index] ?? null;

                if ($originalObject && $originalObject->root_object_id) {
                    // This is a child object - nest it under its parent
                    $parentId                  = $originalObject->root_object_id;
                    $childObjects[$parentId][] = [
                        'type'   => $objectType,
                        'object' => $formattedObject,
                    ];
                } else {
                    $rootObjects[] = $formattedObject;
                }
            }

            // Attach children to their parents
            foreach ($rootObjects as &$rootObject) {
                $rootId = $rootObject['id'];

                if (isset($childObjects[$rootId])) {
                    foreach ($childObjects[$rootId] as $child) {
                        $childType = $child['type'];
                        $rootObject['children'][$childType] ??= [];
                        $rootObject['children'][$childType][] = $child['object'];
                    }
                }
            }

            $typeData['objects'] = $rootObjects;
        }

        // Also nest children from other types under their root objects
        $this->nestCrossTypeChildren($objectTypes, $objectMap, $extractedData);

        return $objectTypes;
    }

    /**
     * Nest child objects from different types under their root parent objects.
     */
    protected function nestCrossTypeChildren(array &$objectTypes, array $objectMap, array $extractedData): void
    {
        foreach ($extractedData as $childType => $childObjects) {
            foreach ($childObjects as $childIndex => $childObj) {
                if (!$childObj->root_object_id) {
                    continue; // Not a child object
                }

                $parentObj = $objectMap[$childObj->root_object_id] ?? null;

                if (!$parentObj) {
                    continue; // Parent not in our extracted data
                }

                $parentType = $parentObj->type;

                // Skip if same type (already handled in buildHierarchy)
                if ($parentType === $childType) {
                    continue;
                }

                // Find the parent in objectTypes and nest this child
                foreach ($objectTypes[$parentType]['objects'] ?? [] as &$parentFormatted) {
                    if ($parentFormatted['id'] === $childObj->root_object_id) {
                        $formattedChild = $this->formatTeamObject($childObj);
                        $parentFormatted['children'][$childType] ??= [];
                        $parentFormatted['children'][$childType][] = $formattedChild;
                        break;
                    }
                }
            }
        }
    }
}
