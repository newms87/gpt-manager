<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Services\Task\ArtifactsMergeService;
use App\Traits\HasDebugLogging;

/**
 * Applies resolution decisions to existing merged artifacts.
 * Moves files between groups based on LLM resolution results.
 */
class ArtifactResolutionService
{
    use HasDebugLogging;

    /**
     * Apply resolution decisions to existing merged artifacts.
     * Moves files between groups based on the agent's final decisions.
     *
     * @param  TaskRun  $taskRun  The task run containing merged artifacts
     * @param  array  $resolutionContent  The resolution artifact's json_content with final group assignments
     */
    public function applyResolutionToMergedArtifacts(TaskRun $taskRun, array $resolutionContent): void
    {
        static::logDebug('Applying resolution decisions to merged artifacts');

        $resolutionGroups = $resolutionContent['groups'] ?? [];

        if (empty($resolutionGroups)) {
            static::logDebug('No resolution groups found');

            return;
        }

        // Build a map of file_id => group_name from resolution
        $fileToResolvedGroup = $this->buildFileToGroupMapping($taskRun, $resolutionGroups);

        if (empty($fileToResolvedGroup)) {
            static::logDebug('No file resolutions to apply');

            return;
        }

        // Get existing merged artifacts from the task run output
        $mergedArtifacts = $taskRun->outputArtifacts()->get();

        if ($mergedArtifacts->isEmpty()) {
            static::logDebug('No merged artifacts found to update');

            return;
        }

        static::logDebug('Found ' . $mergedArtifacts->count() . ' merged artifacts to update');

        // For each resolved file, move it to the correct group
        foreach ($fileToResolvedGroup as $fileId => $targetGroupName) {
            $this->moveFileToGroup($fileId, $targetGroupName, $mergedArtifacts, $taskRun);
        }

        static::logDebug('Resolution application completed');
    }

    /**
     * Build a mapping of file_id => group_name from resolution groups.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $resolutionGroups  Groups from resolution artifact
     * @return array Map of file_id => group_name
     */
    protected function buildFileToGroupMapping(TaskRun $taskRun, array $resolutionGroups): array
    {
        $fileToResolvedGroup = [];

        foreach ($resolutionGroups as $group) {
            $groupName = $group['name']  ?? null;
            $files     = $group['files'] ?? [];

            if (!$groupName) {
                continue;
            }

            foreach ($files as $fileData) {
                // Handle both old format (integer) and new format (object)
                if (is_int($fileData)) {
                    $pageNumber = $fileData;
                } else {
                    $pageNumber = $fileData['page_number'] ?? null;
                }

                if ($pageNumber === null) {
                    continue;
                }

                // Map page_number to file_id by looking at input artifacts
                foreach ($taskRun->inputArtifacts as $inputArtifact) {
                    $storedFile         = $inputArtifact->storedFiles ? $inputArtifact->storedFiles->first() : null;
                    $artifactPageNumber = $storedFile?->page_number ?? null;

                    if ($artifactPageNumber === $pageNumber) {
                        $fileToResolvedGroup[$inputArtifact->id] = $groupName;
                        static::logDebug("Resolution: Page $pageNumber (file {$inputArtifact->id}) -> '$groupName'");
                        break;
                    }
                }
            }
        }

        return $fileToResolvedGroup;
    }

    /**
     * Move a file from its current group to the target group.
     *
     * @param  int  $fileId  Input artifact ID
     * @param  string  $targetGroupName  Target group name
     * @param  \Illuminate\Support\Collection  $mergedArtifacts  Merged artifacts collection
     * @param  TaskRun  $taskRun  The task run
     */
    protected function moveFileToGroup(int $fileId, string $targetGroupName, $mergedArtifacts, TaskRun $taskRun): void
    {
        static::logDebug("Processing file $fileId -> '$targetGroupName'");

        // Find which merged artifact currently contains this file
        $sourceArtifact = null;
        $targetArtifact = null;

        foreach ($mergedArtifacts as $artifact) {
            $groupName = $artifact->meta['group_name'] ?? null;

            // Check if this artifact's children include a copy of the input artifact
            $hasFile = false;
            foreach ($artifact->children as $child) {
                if ($child->parent_artifact_id == $fileId) {
                    $hasFile = true;
                    break;
                }
            }

            if ($hasFile && $groupName !== $targetGroupName) {
                $sourceArtifact = $artifact;
                static::logDebug("  Found file in source group: '$groupName'");
            } elseif ($groupName === $targetGroupName) {
                $targetArtifact = $artifact;
                static::logDebug("  Found target group: '$targetGroupName'");
            }
        }

        // If file is already in the correct group, skip
        if (!$sourceArtifact && $targetArtifact) {
            static::logDebug('  File already in correct group, skipping');

            return;
        }

        // If we found a source but no target, create a new group
        if ($sourceArtifact && !$targetArtifact) {
            $targetArtifact = $this->createNewGroup($fileId, $targetGroupName, $taskRun);
        }

        // Move the file from source to target
        if ($sourceArtifact && $targetArtifact) {
            $this->transferFileBetweenArtifacts($fileId, $sourceArtifact, $targetArtifact, $taskRun);
        }
    }

    /**
     * Create a new merged artifact for a group.
     *
     * @param  int  $fileId  Input artifact ID
     * @param  string  $groupName  Group name
     * @param  TaskRun  $taskRun  The task run
     * @return Artifact The newly created merged artifact
     */
    protected function createNewGroup(int $fileId, string $groupName, TaskRun $taskRun): Artifact
    {
        static::logDebug("  Creating new group: '$groupName'");

        // Get the input artifact
        $inputArtifact = $taskRun->inputArtifacts()->where('artifacts.id', $fileId)->first();

        if (!$inputArtifact) {
            static::logDebug("  ERROR: Could not find input artifact $fileId");

            return null;
        }

        // Create a copy for the new group
        $artifactCopy = $inputArtifact->copy();

        // Create new merged artifact for this group
        $targetArtifact       = app(ArtifactsMergeService::class)->merge([$artifactCopy]);
        $targetArtifact->name = $groupName;
        $targetArtifact->meta = [
            'group_name'  => $groupName,
            'description' => 'Group created during resolution',
            'file_count'  => 1,
        ];
        $targetArtifact->save();

        // Add to task run outputs
        $taskRun->outputArtifacts()->attach($targetArtifact->id, ['category' => 'output']);

        static::logDebug("  Created new merged artifact: $targetArtifact");

        return $targetArtifact;
    }

    /**
     * Transfer a file from source artifact to target artifact.
     *
     * @param  int  $fileId  Input artifact ID
     * @param  Artifact  $sourceArtifact  Source merged artifact
     * @param  Artifact  $targetArtifact  Target merged artifact
     * @param  TaskRun  $taskRun  The task run
     */
    protected function transferFileBetweenArtifacts(int $fileId, Artifact $sourceArtifact, Artifact $targetArtifact, TaskRun $taskRun): void
    {
        static::logDebug("  Moving file from '{$sourceArtifact->meta['group_name']}' to '{$targetArtifact->meta['group_name']}'");

        // Find the child artifact (copy) from source
        $childArtifact = null;
        foreach ($sourceArtifact->children as $child) {
            if ($child->parent_artifact_id == $fileId) {
                $childArtifact = $child;
                break;
            }
        }

        if (!$childArtifact) {
            static::logDebug('  ERROR: Could not find child artifact in source');

            return;
        }

        // Remove from source artifact's children
        $sourceArtifact->children()->detach($childArtifact->id);
        $sourceArtifact->updateRelationCounter('children');

        // Update source artifact meta
        $sourceArtifact->meta = array_merge($sourceArtifact->meta ?? [], [
            'file_count' => $sourceArtifact->children()->count(),
        ]);
        $sourceArtifact->save();

        // If source artifact now has no children, delete it
        if ($sourceArtifact->children()->count() === 0) {
            static::logDebug("  Source group '{$sourceArtifact->meta['group_name']}' now empty - removing from outputs");
            $taskRun->outputArtifacts()->detach($sourceArtifact->id);
            $sourceArtifact->delete();
        }

        // Add to target artifact's children
        $targetArtifact->children()->attach($childArtifact->id);
        $targetArtifact->updateRelationCounter('children');

        // Update target artifact meta
        $targetArtifact->meta = array_merge($targetArtifact->meta ?? [], [
            'file_count' => $targetArtifact->children()->count(),
        ]);
        $targetArtifact->save();

        static::logDebug('  Successfully moved file');
    }
}
