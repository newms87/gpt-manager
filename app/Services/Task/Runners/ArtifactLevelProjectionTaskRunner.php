<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskArtifactFilter;
use App\Services\AgentThread\ArtifactFilterService;
use Illuminate\Support\Collection;
use Newms87\Danx\Helpers\ArrayHelper;

class ArtifactLevelProjectionTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Artifact Level Projection';

    /**
     * Run the artifact level projection task
     */
    public function run(): void
    {
        $sourceLevels  = $this->config('source_levels') ?: [0]; // Default to top level
        $targetLevels  = $this->config('target_levels') ?: [1]; // Default to first child level
        $textSeparator = $this->config('text_separator') ?: "\n\n";
        $textPrefix    = $this->config('text_prefix') ?: '';

        // Get all input artifacts
        $allArtifacts = $this->taskProcess->inputArtifacts()->get();

        // Group artifacts by their hierarchy structure to ensure proper projection
        $hierarchyGroups = $this->groupArtifactsByHierarchy($allArtifacts);

        $outputArtifacts = collect();

        // For each hierarchy group, apply the projection between appropriate source and target levels
        foreach ($hierarchyGroups as $hierarchyGroup) {
            // Filter artifacts by levels
            $sourceArtifacts = $this->getArtifactsByLevels($hierarchyGroup, $sourceLevels);
            $targetArtifacts = $this->getArtifactsByLevels($hierarchyGroup, $targetLevels);

            if ($sourceArtifacts->isEmpty() || $targetArtifacts->isEmpty()) {
                continue;
            }

            // For each target, project applicable source artifacts
            foreach ($targetArtifacts as $targetArtifact) {
                // Find all source artifacts that are in the same hierarchy branch as this target
                $relatedSourceArtifacts = $this->getRelatedSourceArtifacts($targetArtifact, $sourceArtifacts);

                if ($relatedSourceArtifacts->isEmpty()) {
                    continue;
                }

                // Create a new artifact to hold the projected data
                $projectedArtifact = $this->createProjectedArtifact($targetArtifact, $relatedSourceArtifacts, $textSeparator, $textPrefix);

                $outputArtifacts->push($projectedArtifact);
            }
        }

        // Complete the task with the projected artifacts
        $this->complete($outputArtifacts);
    }

    /**
     * Group artifacts by their hierarchy structure
     * This ensures that artifacts from different top-level structures are not mixed
     *
     * @param  Collection  $artifacts  All artifacts
     * @return array Array of artifact collections, grouped by root ancestor
     */
    private function groupArtifactsByHierarchy(Collection $artifacts): array
    {
        $hierarchyGroups = [];

        foreach ($artifacts as $artifact) {
            $rootAncestor = $this->findRootAncestor($artifact);
            $rootId       = $rootAncestor->id;

            if (!isset($hierarchyGroups[$rootId])) {
                $hierarchyGroups[$rootId] = collect();
            }

            $hierarchyGroups[$rootId]->push($artifact);
        }

        return $hierarchyGroups;
    }

    /**
     * Find the root ancestor of an artifact (top of hierarchy)
     *
     * @param  Artifact  $artifact  Artifact to find root for
     * @return Artifact Root ancestor
     */
    private function findRootAncestor(Artifact $artifact): Artifact
    {
        if (!$artifact->parent_artifact_id) {
            return $artifact;
        }

        $parent = $artifact->parent;
        if (!$parent) {
            return $artifact;
        }

        return $this->findRootAncestor($parent);
    }

    /**
     * Get artifacts that match the specified levels
     *
     * @param  Collection  $artifacts  All artifacts
     * @param  array  $levels  Levels to filter by
     * @return Collection Filtered artifacts
     */
    private function getArtifactsByLevels(Collection $artifacts, array $levels): Collection
    {
        $filteredArtifacts = collect();

        foreach ($artifacts as $artifact) {
            $this->processArtifactByLevel($artifact, $levels, $filteredArtifacts);
        }

        return $filteredArtifacts;
    }

    /**
     * Process an artifact and its children based on the specified levels
     *
     * @param  Artifact  $artifact  The artifact to process
     * @param  array  $levels  The levels to include
     * @param  Collection  $result  The collection to add matching artifacts to
     * @param  int  $currentLevel  The current nesting level (0 = top level)
     */
    private function processArtifactByLevel(Artifact $artifact, array $levels, Collection &$result, int $currentLevel = 0): void
    {
        // If the current level is in the specified levels, add this artifact to the result
        if (in_array($currentLevel, $levels)) {
            $result->push($artifact);
        }

        // Process children recursively at the next level
        if ($artifact->children()->exists()) {
            foreach ($artifact->children as $child) {
                $this->processArtifactByLevel($child, $levels, $result, $currentLevel + 1);
            }
        }
    }

    /**
     * Get source artifacts that are related to the given target artifact
     * (in the same branch of the hierarchy)
     *
     * @param  Artifact  $targetArtifact  Target artifact
     * @param  Collection  $sourceArtifacts  All source artifacts
     * @return Collection Related source artifacts
     */
    private function getRelatedSourceArtifacts(Artifact $targetArtifact, Collection $sourceArtifacts): Collection
    {
        $relatedSources = collect();

        // For downward projection: find ancestors of the target that are in the source artifacts
        $currentArtifact = $targetArtifact;
        while ($currentArtifact) {
            if ($sourceArtifacts->contains('id', $currentArtifact->id)) {
                $relatedSources->push($currentArtifact);
            }

            // Move up the hierarchy
            $currentArtifact = $currentArtifact->parent;
        }

        // For upward projection: find descendants of the target that are in the source artifacts
        $this->findRelatedDescendants($targetArtifact, $sourceArtifacts, $relatedSources);

        return $relatedSources;
    }

    /**
     * Find descendants of the target artifact that are in the source artifacts
     *
     * @param  Artifact  $artifact  Current artifact to check descendants of
     * @param  Collection  $sourceArtifacts  All source artifacts
     * @param  Collection  $result  Collection to add matching artifacts to
     */
    private function findRelatedDescendants(Artifact $artifact, Collection $sourceArtifacts, Collection &$result): void
    {
        if ($artifact->children()->exists()) {
            foreach ($artifact->children as $child) {
                if ($sourceArtifacts->contains('id', $child->id)) {
                    $result->push($child);
                }

                // Recursively check this child's descendants
                $this->findRelatedDescendants($child, $sourceArtifacts, $result);
            }
        }
    }

    /**
     * Create a projected artifact by applying task artifact filters
     *
     * @param  Artifact  $targetArtifact  Target artifact to project data onto
     * @param  Collection  $sourceArtifacts  Source artifacts to project data from
     * @param  string  $textSeparator  Separator for text content
     * @param  string  $textPrefix  Prefix for text content
     * @return Artifact The target artifact with projected data
     */
    private function createProjectedArtifact(Artifact $targetArtifact, Collection $sourceArtifacts, string $textSeparator, string $textPrefix): Artifact
    {
        // Check if there are task artifact filters for the source task definitions
        foreach ($sourceArtifacts as $sourceArtifact) {
            $sourceTaskDefinitionId = $sourceArtifact->task_definition_id;

            $filterService = app(ArtifactFilterService::class)->setArtifact($sourceArtifact);

            // Find a filter that matches this source task definition and the current task runner
            $filter = $this->findTaskArtifactFilter($sourceTaskDefinitionId);

            // Only apply projection if a filter is defined
            if ($filter) {
                $filterService->setFilter($filter);
            }

            // Project text content
            if ($filterService->hasText()) {
                if ($targetArtifact->text_content) {
                    $targetArtifact->text_content .= $textSeparator;
                }
                $targetArtifact->text_content .= $textPrefix . $filterService->getTextContent();
            }

            // Project JSON content
            if ($filterService->hasJson()) {
                $filteredJson = $filterService->getFilteredJson();
                if ($filteredJson) {
                    $targetArtifact->json_content = ArrayHelper::mergeArraysRecursivelyUnique(
                        $targetArtifact->json_content ?? [],
                        $filteredJson
                    );
                }
            }

            // Project meta data
            if ($filterService->hasMeta()) {
                $filteredMeta = $filterService->getFilteredMeta();
                if ($filteredMeta) {
                    $targetArtifact->meta = ArrayHelper::mergeArraysRecursivelyUnique(
                        $targetArtifact->meta ?? [],
                        $filteredMeta
                    );
                }
            }

            // Project files
            if ($filterService->hasFiles() && $sourceArtifact->storedFiles->isNotEmpty()) {
                $targetArtifact->storedFiles()->syncWithoutDetaching($sourceArtifact->storedFiles->pluck('id'));
            }
        }

        // Always save the target artifact
        $targetArtifact->save();

        return $targetArtifact;
    }

    /**
     * Find a task artifact filter for the given source task definition
     */
    private function findTaskArtifactFilter(?int $sourceTaskDefinitionId): ?TaskArtifactFilter
    {
        // Skip if source task definition is missing
        if (!$sourceTaskDefinitionId) {
            return null;
        }

        // Find a filter that matches this source task definition and the current task definition
        return TaskArtifactFilter::query()
            ->where('source_task_definition_id', $sourceTaskDefinitionId)
            ->where('target_task_definition_id', $this->taskDefinition->id)
            ->first();
    }
}
