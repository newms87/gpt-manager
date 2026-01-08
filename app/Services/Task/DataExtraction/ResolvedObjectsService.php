<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Helpers\LockHelper;

/**
 * Handles storage and retrieval of resolved TeamObject IDs in artifact metadata.
 * Uses locking to prevent race conditions when multiple processes update concurrently.
 */
class ResolvedObjectsService
{
    use HasDebugLogging;

    /**
     * Store a single resolved object ID in all input artifacts of a task process.
     */
    public function storeInProcessArtifacts(TaskProcess $taskProcess, string $objectType, int $objectId): void
    {
        $this->storeMultipleInProcessArtifacts($taskProcess, $objectType, [$objectId]);
    }

    /**
     * Store multiple resolved object IDs in all input artifacts of a task process.
     * Uses locking to prevent race conditions.
     *
     * @param  array<int>  $objectIds
     */
    public function storeMultipleInProcessArtifacts(TaskProcess $taskProcess, string $objectType, array $objectIds): void
    {
        if (empty($objectIds)) {
            return;
        }

        foreach ($taskProcess->inputArtifacts as $artifact) {
            $this->storeInArtifact($artifact, $objectType, $objectIds);
        }

        static::logDebug('Stored resolved objects in process artifacts', [
            'object_type'    => $objectType,
            'object_count'   => count($objectIds),
            'artifact_count' => $taskProcess->inputArtifacts->count(),
        ]);
    }

    /**
     * Store resolved object IDs in a single artifact with locking.
     *
     * @param  array<int>  $objectIds
     */
    public function storeInArtifact(Artifact $artifact, string $objectType, array $objectIds): void
    {
        LockHelper::acquire($artifact);

        try {
            $artifact->refresh();
            $meta            = $artifact->meta           ?? [];
            $resolvedObjects = $meta['resolved_objects'] ?? [];

            if (!isset($resolvedObjects[$objectType])) {
                $resolvedObjects[$objectType] = [];
            }

            foreach ($objectIds as $objectId) {
                if (!in_array($objectId, $resolvedObjects[$objectType], true)) {
                    $resolvedObjects[$objectType][] = $objectId;
                }
            }

            $meta['resolved_objects'] = $resolvedObjects;
            $artifact->meta           = $meta;
            $artifact->save();
        } finally {
            LockHelper::release($artifact);
        }
    }

    /**
     * Get resolved object IDs from all artifacts, combined and deduplicated.
     *
     * @param  Collection<int, Artifact>  $artifacts
     * @return array<string, array<int>> Object type => array of IDs
     */
    public function combineFromArtifacts(Collection $artifacts): array
    {
        $combined = [];

        foreach ($artifacts as $artifact) {
            $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

            foreach ($resolvedObjects as $type => $ids) {
                if (!isset($combined[$type])) {
                    $combined[$type] = [];
                }
                $combined[$type] = array_values(array_unique(array_merge($combined[$type], $ids)));
            }
        }

        return $combined;
    }

    /**
     * Get resolved object IDs for a specific type from artifacts.
     *
     * @param  Collection<int, Artifact>  $artifacts
     * @return array<int>
     */
    public function getForType(Collection $artifacts, string $objectType): array
    {
        $combined = $this->combineFromArtifacts($artifacts);

        return $combined[$objectType] ?? [];
    }
}
