<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Newms87\Danx\Helpers\ArrayHelper;

class ArtifactsSplitterService
{
    const string
        ARTIFACT_SPLIT_BY_NODE = 'Node',
        ARTIFACT_SPLIT_BY_ARTIFACT = 'Artifact',
        ARTIFACT_SPLIT_BY_TOP_LEVEL = 'Top-Level',
        ARTIFACT_SPLIT_BY_COMBINATIONS = 'Combinations';

    /**
     * Split the artifacts into groups based on the split mode.
     *
     * If levels is set, it will only include artifacts that are in the given nested levels. The resulting collection
     * is a flattened list of artifacts.
     *
     * For example,
     * - Artifact A has 2 levels of nested artifacts
     * - Artifact B has only the top level (0)
     * - Artifact C has 1 level of nested artifacts
     *
     * Scenario A:
     *  If only level 0 is selected (same as empty), top level artifacts of A,B and C are returned (their children are
     *  still associated however in the hierarchy)
     *
     * Scenario B:
     *  If only level 1 is selected, only artifacts of A and C that are in the first level of nesting are returned (top
     *  level artifacts are NOT returned)
     *
     * Scenario C:
     *  If level 0 and 2 are selected, only top level artifacts from A,B and C and the artifacts at the 2nd level of A
     *  will be returned in a flattened list
     *
     * @param Artifact[]|Collection $artifacts
     */
    public static function split(string $splitMode, array|Collection|EloquentCollection $artifacts, array $levels = null): Collection
    {
        $artifacts = collect($artifacts);

        // Filter artifacts by levels if specified
        if ($levels !== null) {
            $filteredArtifacts = collect();

            // Process artifacts based on their level
            foreach($artifacts as $artifact) {
                self::processArtifactByLevel($artifact, $levels, $filteredArtifacts);
            }

            $artifacts = $filteredArtifacts;
        }

        if ($splitMode === self::ARTIFACT_SPLIT_BY_NODE) {
            return self::byNode($artifacts);
        } elseif ($splitMode === self::ARTIFACT_SPLIT_BY_ARTIFACT) {
            return self::byArtifact($artifacts);
        } elseif ($splitMode === self::ARTIFACT_SPLIT_BY_COMBINATIONS) {
            return self::byCombinations($artifacts);
        } elseif ($splitMode === self::ARTIFACT_SPLIT_BY_TOP_LEVEL) {
            return self::byTopLevel($artifacts);
        }

        return self::allTogether($artifacts);
    }

    /**
     * Process an artifact and its children based on the specified levels
     *
     * @param Artifact   $artifact     The artifact to process
     * @param array      $levels       The levels to include
     * @param Collection $result       The collection to add matching artifacts to
     * @param int        $currentLevel The current nesting level (0 = top level)
     */
    private static function processArtifactByLevel(Artifact $artifact, array $levels, Collection &$result, int $currentLevel = 0): void
    {
        // If the current level is in the specified levels, add this artifact to the result
        if (in_array($currentLevel, $levels)) {
            $result->push($artifact);
        }

        // Process children recursively at the next level
        if ($artifact->children()->exists()) {
            foreach($artifact->children as $child) {
                self::processArtifactByLevel($child, $levels, $result, $currentLevel + 1);
            }
        }
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
            $artifactGroups[$artifact->task_definition_id ?? 'default'][] = $artifact;
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
     * Split the artifacts by creating one group per top-level artifact
     * Each group will contain a top-level artifact and any of its descendants that match the levels filter
     * 
     * @param Artifact[]|Collection $artifacts
     */
    public static function byTopLevel(Collection|EloquentCollection $artifacts): Collection
    {
        // First, identify all the top-level artifacts (those without a parent)
        // and organize all artifacts by their root ancestor
        $topLevelArtifacts = collect();
        $artifactGroups = collect();
        
        foreach($artifacts as $artifact) {
            // Find the top-level ancestor for this artifact
            $topLevelAncestor = self::findTopLevelAncestor($artifact);
            
            // Save the top-level artifact if we haven't seen it before
            if (!$topLevelArtifacts->contains('id', $topLevelAncestor->id)) {
                $topLevelArtifacts->push($topLevelAncestor);
            }
            
            // Add this artifact to the group of its top-level ancestor
            if (!isset($artifactGroups[$topLevelAncestor->id])) {
                $artifactGroups[$topLevelAncestor->id] = collect();
            }
            
            $artifactGroups[$topLevelAncestor->id]->push($artifact);
        }
        
        // Return the groups organized by top-level ancestors
        return $artifactGroups->values();
    }
    
    /**
     * Find the top-level ancestor of an artifact
     * 
     * @param Artifact $artifact The artifact to find the top-level ancestor for
     * @return Artifact The top-level ancestor (or the artifact itself if it's already top-level)
     */
    private static function findTopLevelAncestor(Artifact $artifact): Artifact
    {
        if ($artifact->parent_artifact_id === null) {
            return $artifact;
        }
        
        // Recursively find the topmost parent
        $parent = $artifact->parent;
        if (!$parent) {
            // If parent relationship is broken, treat this as a top-level artifact
            return $artifact;
        }
        
        return self::findTopLevelAncestor($parent);
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

        return collect(ArrayHelper::crossProduct($artifactGroups->all()));
    }
}
