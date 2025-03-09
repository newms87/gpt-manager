<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Log;
use Newms87\Danx\Helpers\ArrayHelper;

class ArtifactsSplitterService
{
    const string
        ARTIFACT_SPLIT_BY_NODE = 'Node',
        ARTIFACT_SPLIT_BY_ARTIFACT = 'Artifact',
        ARTIFACT_SPLIT_BY_COMBINATIONS = 'Combinations';

    /**
     * Split the artifacts into groups based on the split mode
     * @param Artifact[]|Collection $artifacts
     */
    public static function split(string $splitMode, array|Collection|EloquentCollection $artifacts): Collection
    {
        $artifacts = collect($artifacts);

        if ($splitMode === self::ARTIFACT_SPLIT_BY_NODE) {
            return self::byNode($artifacts);
        } elseif ($splitMode === self::ARTIFACT_SPLIT_BY_ARTIFACT) {
            return self::byArtifact($artifacts);
        } elseif ($splitMode === self::ARTIFACT_SPLIT_BY_COMBINATIONS) {
            return self::byCombinations($artifacts);
        }

        return self::allTogether($artifacts);
    }

    /**
     * Keep all the artifacts together in a single group
     *
     * @param Artifact[]|Collection $artifacts
     */
    public static function allTogether(Collection|EloquentCollection $artifacts): Collection
    {
        return collect([$artifacts]);
    }

    /**
     * Split the artifacts by the node that created them
     * @param Artifact[]|Collection $artifacts
     */
    public static function byNode(Collection|EloquentCollection $artifacts): Collection
    {
        $artifactGroups = [];
        foreach($artifacts as $artifact) {
            $taskProcess = $artifact->getTaskProcessThatCreatedArtifact();

            if (!$taskProcess) {
                Log::debug("No task process found for artifact: $artifact");
                $artifactGroups['default'][] = $artifact;
                continue;
            }

            $artifactGroups[$taskProcess->taskRun->task_workflow_node_id][] = $artifact;
        }

        return collect($artifactGroups)->values();
    }

    /**
     * Split the artifacts so there is 1 in each group
     * @param Artifact[]|Collection $artifacts
     */
    public static function byArtifact(Collection|EloquentCollection $artifacts): Collection
    {
        return $artifacts->groupBy('id')->values();
    }

    /**
     * Split the artifacts by all possible combinations of artifacts across nodes
     * NOTE: This is a cross product of all node groups of artifacts
     *
     * (ie: Node A [A1, A2, A3], Node B [B1, B2] => [A1, B1], [A1, B2], [A2, B1], [A2, B2], [A3, B1], [A3, B2])
     * @param Artifact[]|Collection $artifacts
     */
    public static function byCombinations(Collection|EloquentCollection $artifacts): Collection
    {
        $artifactGroups = static::byNode($artifacts);

        return ArrayHelper::crossProduct($artifactGroups);
    }
}
