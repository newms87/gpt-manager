<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Traits\HasDebugLogging;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Collects and rolls up all extracted data from child artifacts into a single
 * structured JSON format stored in the parent output artifact's json_content.
 *
 * Builds the rollup entirely from artifact data - ZERO TeamObject queries.
 *
 * Output format:
 * ```yaml
 * extracted_at: 2026-01-13T23:08:53+00:00
 * objects:
 *   - id: 1
 *     type: Demand
 *     name: Abdi, Abdinasir
 *     accident_date: 2017-10-23  # Attributes flattened directly on object
 *     client:                     # Relationship key (snake_case of type)
 *       id: 2
 *       type: Client
 *       ...
 * summary:
 *   total_objects: 54
 *   by_type:
 *     Demand: 1
 *     Client: 1
 * ```
 */
class ExtractionRollupService
{
    use HasDebugLogging;

    /**
     * Roll up all extracted data from a TaskRun into its output artifact.
     * Builds the rollup entirely from artifact data - no TeamObject queries.
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

        // Get all child extraction artifacts
        $childArtifacts = $this->getChildExtractionArtifacts($parentArtifact);

        // Build rollup from artifact data only
        $rollupStructure = $this->buildRollupFromArtifacts($childArtifacts);

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
     * Get all descendant extraction artifacts under the parent artifact.
     * Includes both direct children and nested children (artifacts under page artifacts).
     *
     * @return Collection<Artifact>
     */
    protected function getChildExtractionArtifacts(Artifact $parentArtifact): Collection
    {
        // Get direct children IDs
        $directChildIds = Artifact::where('parent_artifact_id', $parentArtifact->id)
            ->pluck('id');

        // Get all extraction artifacts that are either:
        // 1. Direct children of the parent artifact
        // 2. Nested children (under page artifacts which are children of parent)
        return Artifact::where(function ($query) use ($parentArtifact, $directChildIds) {
            $query->where('parent_artifact_id', $parentArtifact->id)
                ->orWhereIn('parent_artifact_id', $directChildIds);
        })
            ->whereNotNull('json_content')
            ->where(function ($query) {
                // Filter for extraction operations (Extract Identity, Extract Remaining)
                $query->where('meta->operation', 'like', 'Extract%');
            })
            ->get();
    }

    /**
     * Build the rollup structure entirely from artifact data.
     * No TeamObject queries - uses json_content and meta from artifacts.
     *
     * Uses artifact meta for:
     * - relationship_key: The exact schema property name (e.g., "providers", "care_summary")
     * - is_array_type: Whether the schema defines this as an array (determines cardinality)
     *
     * @param  Collection<Artifact>  $artifacts
     */
    public function buildRollupFromArtifacts(Collection $artifacts): array
    {
        $objectsById      = [];
        $childrenByParent = []; // parent_id => [child objects with type info and relationship key]

        static::logDebug('Building rollup from artifacts', ['count' => $artifacts->count()]);

        // First pass: extract object data from artifact json_content
        foreach ($artifacts as $artifact) {
            $objectData = $this->extractObjectFromArtifact($artifact);

            if (!$objectData || !isset($objectData['id'])) {
                continue;
            }

            $objectId = $objectData['id'];
            $parentId = $artifact->meta['parent_id'] ?? null;

            // Store object data keyed by ID (may be updated by multiple artifacts)
            if (!isset($objectsById[$objectId])) {
                $objectsById[$objectId] = $objectData;
            } else {
                // Merge additional data from other artifacts for the same object
                $objectsById[$objectId] = array_merge($objectsById[$objectId], $objectData);
            }

            // Track parent-child relationships (deduplicated by object ID)
            if ($parentId !== null) {
                $childrenByParent[$parentId] ??= [];

                // Get relationship info from artifact meta (schema is source of truth)
                $childType       = $objectData['type']                   ?? 'Unknown';
                $relationshipKey = $artifact->meta['relationship_key']   ?? Str::snake($childType);
                $isArrayType     = $artifact->meta['is_array_type']      ?? false;

                // Check if this child ID already exists for this parent (deduplication)
                $existingChildIds = array_column($childrenByParent[$parentId], 'id');
                if (!in_array($objectId, $existingChildIds, true)) {
                    $childrenByParent[$parentId][] = [
                        'id'               => $objectId,
                        'type'             => $childType,
                        'relationship_key' => $relationshipKey,
                        'is_array_type'    => $isArrayType,
                    ];
                }
            }
        }

        // Find root objects (artifacts with no parent_id in meta)
        $rootObjects = [];

        foreach ($artifacts as $artifact) {
            $parentId = $artifact->meta['parent_id'] ?? null;

            if ($parentId !== null) {
                continue;
            }

            $objectData = $this->extractObjectFromArtifact($artifact);

            if (!$objectData || !isset($objectData['id'])) {
                continue;
            }

            $rootId = $objectData['id'];

            // Skip if we've already processed this root
            if (isset($rootObjects[$rootId])) {
                continue;
            }

            // Build nested structure starting from this root
            $rootObjects[$rootId] = $this->nestChildren(
                $objectsById[$rootId] ?? $objectData,
                $objectsById,
                $childrenByParent
            );
        }

        // Build summary
        $byType = [];

        foreach ($objectsById as $obj) {
            $type          = $obj['type'] ?? 'Unknown';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'extracted_at' => Carbon::now()->toIso8601String(),
            'objects'      => array_values($rootObjects),
            'summary'      => [
                'total_objects' => count($objectsById),
                'by_type'       => $byType,
            ],
        ];
    }

    /**
     * Extract the leaf object data from an artifact's json_content.
     * Handles hierarchical structures by finding the deepest nested object.
     */
    protected function extractObjectFromArtifact(Artifact $artifact): ?array
    {
        $jsonContent = $artifact->json_content;

        if (empty($jsonContent) || !is_array($jsonContent)) {
            return null;
        }

        // Find the leaf object in the hierarchical structure
        return $this->findLeafObject($jsonContent);
    }

    /**
     * Recursively find the leaf object in a hierarchical json_content structure.
     * The leaf is the deepest nested object with 'id' and 'type'.
     */
    protected function findLeafObject(array $data): ?array
    {
        // Must have id and type to be a valid object
        if (!isset($data['id']) || !isset($data['type'])) {
            return null;
        }

        // Look for nested children (any key that contains an array with 'id' and 'type')
        foreach ($data as $key => $value) {
            // Skip standard metadata keys
            if (in_array($key, ['id', 'type', 'name', 'date'])) {
                continue;
            }

            if (is_array($value)) {
                // Check if it's a single nested object
                if (isset($value['id']) && isset($value['type'])) {
                    $leaf = $this->findLeafObject($value);

                    if ($leaf !== null) {
                        return $leaf;
                    }
                }

                // Check if it's an array of nested objects
                if (!empty($value) && isset($value[0]) && is_array($value[0]) && isset($value[0]['id'])) {
                    // Return the first leaf from the array
                    $leaf = $this->findLeafObject($value[0]);

                    if ($leaf !== null) {
                        return $leaf;
                    }
                }
            }
        }

        // No nested children found - this is the leaf
        // Return all data except nested objects (we want flat attributes)
        return $this->extractFlatAttributes($data);
    }

    /**
     * Extract flat attributes from object data, excluding nested objects.
     */
    protected function extractFlatAttributes(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip nested objects and arrays of objects
            if (is_array($value) && (isset($value['id']) || (isset($value[0]) && is_array($value[0])))) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Recursively nest children under their parent object.
     *
     * Uses schema-defined cardinality from artifact meta:
     * - is_array_type=true: Always use array (even with single child)
     * - is_array_type=false: Use object directly (first child only)
     *
     * @param  array  $object  The current object data
     * @param  array<int, array>  $objectsById  All objects keyed by ID
     * @param  array<int, array>  $childrenByParent  Children grouped by parent ID
     * @param  array<int, bool>  $visited  Track visited objects to prevent cycles
     */
    protected function nestChildren(
        array $object,
        array $objectsById,
        array $childrenByParent,
        array &$visited = []
    ): array {
        $objectId = $object['id'];

        // Prevent infinite loops from circular references
        if (isset($visited[$objectId])) {
            return $object;
        }

        $visited[$objectId] = true;

        // Get children for this object
        $children = $childrenByParent[$objectId] ?? [];

        // Group children by relationship key (not by type)
        // This ensures we use the actual schema property name
        $childrenByRelationKey = [];

        foreach ($children as $childInfo) {
            $childId         = $childInfo['id'];
            $relationshipKey = $childInfo['relationship_key'];
            $isArrayType     = $childInfo['is_array_type'];

            if (!isset($objectsById[$childId])) {
                continue;
            }

            $childrenByRelationKey[$relationshipKey] ??= [
                'is_array_type' => $isArrayType,
                'children'      => [],
            ];

            $childrenByRelationKey[$relationshipKey]['children'][] = $this->nestChildren(
                $objectsById[$childId],
                $objectsById,
                $childrenByParent,
                $visited
            );
        }

        // Add nested children using schema-defined relationship keys and cardinality
        foreach ($childrenByRelationKey as $relationKey => $data) {
            $isArrayType   = $data['is_array_type'];
            $typeChildren  = $data['children'];

            // Respect schema cardinality, NOT child count
            if ($isArrayType) {
                // Schema defines array - always use array
                $object[$relationKey] = $typeChildren;
            } else {
                // Schema defines single object - use first child only
                if (!empty($typeChildren)) {
                    $object[$relationKey] = $typeChildren[0];
                }
            }
        }

        return $object;
    }
}
