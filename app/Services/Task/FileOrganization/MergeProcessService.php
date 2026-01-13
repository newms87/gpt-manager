<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\ArtifactsMergeService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Handles merging window results into final groups.
 */
class MergeProcessService
{
    use HasDebugLogging;

    /**
     * Run the merge process to combine window results into final groups.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  TaskProcess  $taskProcess  The merge process
     * @return array Array containing output artifacts and metadata
     */
    public function runMergeProcess(TaskRun $taskRun, TaskProcess $taskProcess): array
    {
        static::logDebug('Starting merge of window results');

        // Get all window artifacts from window task processes
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', 'Comparison Window')
            ->get();

        $windowArtifacts = collect();
        foreach ($windowProcesses as $process) {
            $windowArtifacts = $windowArtifacts->merge($process->outputArtifacts);
        }

        static::logDebug('Found ' . $windowArtifacts->count() . ' window artifacts to merge');

        if ($windowArtifacts->isEmpty()) {
            static::logDebug('No window artifacts found, completing with no output');

            return ['artifacts' => [], 'metadata' => []];
        }

        // Merge the windows using the NEW adjacency-based algorithm
        $mergeService = app(FileOrganizationMergeService::class);
        $mergeResult  = $mergeService->mergeWindowResults($windowArtifacts);
        $finalGroups  = $mergeResult['groups'];
        $fileToGroup  = $mergeResult['file_to_group_mapping'];

        static::logDebug('Merge completed: ' . count($finalGroups) . ' final groups');

        // Store adjacency data (belongs_to_previous) on StoredFiles for use by other tasks
        $this->storeAdjacencyDataOnStoredFiles($taskRun, $fileToGroup);

        // Phase 4 of the new algorithm handles low-confidence resolution via adjacency
        // No need for separate LLM resolution of low-confidence files

        // Prepare ALL groups for deduplication (not just similar pairs)
        $duplicateDetector      = app(DuplicateGroupDetector::class);
        $groupsForDeduplication = $duplicateDetector->prepareAllGroupsForResolution($finalGroups, $fileToGroup);

        // Store groups for deduplication in metadata
        $metadata = $this->buildResolutionMetadata($groupsForDeduplication);

        // Create output artifacts for each final group
        $outputArtifacts = $this->createGroupArtifacts($finalGroups, $taskRun, $windowArtifacts);

        static::logDebug('Created ' . count($outputArtifacts) . ' output artifacts');

        return [
            'artifacts' => $outputArtifacts,
            'metadata'  => $metadata,
        ];
    }

    /**
     * Build metadata for issues that need resolution.
     *
     * Low-confidence files are now handled automatically via Phase 4 adjacency resolution.
     * Null groups (blank pages) are handled via configuration in the new algorithm.
     * All groups are sent to LLM for deduplication and spelling correction.
     *
     * @param  array  $groupsForDeduplication  All groups prepared for deduplication
     * @return array Metadata for task process
     */
    protected function buildResolutionMetadata(array $groupsForDeduplication): array
    {
        $metadata = [];

        if (!empty($groupsForDeduplication)) {
            static::logDebug('Prepared ' . count($groupsForDeduplication) . ' groups for deduplication');
            $metadata['groups_for_deduplication'] = $groupsForDeduplication;
        }

        return $metadata;
    }

    /**
     * Create output artifacts for each final group.
     *
     * @param  array  $finalGroups  Final groups from merge
     * @param  TaskRun  $taskRun  The task run
     * @param  Collection  $windowArtifacts  Window artifacts
     * @return array Array of created artifacts
     */
    protected function createGroupArtifacts(array $finalGroups, TaskRun $taskRun, Collection $windowArtifacts): array
    {
        $outputArtifacts = [];

        foreach ($finalGroups as $group) {
            $groupName   = $group['name'];
            $description = $group['description'] ?? '';
            $pageNumbers = $group['files']; // These are page numbers, not artifact IDs

            // Get the artifacts for this group by looking up stored files by page_number
            // Use distinct artifact IDs to avoid duplicates, then fetch the artifacts
            $artifactIds = $taskRun->inputArtifacts()
                ->join('stored_file_storables', function ($join) {
                    $join->on('artifacts.id', '=', 'stored_file_storables.storable_id')
                        ->where('stored_file_storables.storable_type', '=', Artifact::class);
                })
                ->join('stored_files', 'stored_file_storables.stored_file_id', '=', 'stored_files.id')
                ->whereIn('stored_files.page_number', $pageNumbers)
                ->pluck('artifacts.id')
                ->unique();

            if ($artifactIds->isEmpty()) {
                static::logDebug("Group '$groupName': no artifacts found, skipping");

                continue;
            }

            // Fetch the full artifacts by ID and order them by their stored files' page numbers
            $groupArtifacts = Artifact::whereIn('id', $artifactIds)
                ->with(['storedFiles' => function ($query) use ($pageNumbers) {
                    $query->whereIn('page_number', $pageNumbers)
                        ->orderBy('page_number');
                }])
                ->get()
                ->sortBy(function ($artifact) {
                    return $artifact->storedFiles->min('page_number');
                })
                ->values();

            // Create copies of input artifacts to preserve originals
            $artifactCopies = [];
            foreach ($groupArtifacts as $artifact) {
                $artifactCopies[] = $artifact->copy();
            }

            // Create merged artifact for this group
            $mergedArtifact = app(ArtifactsMergeService::class)->merge($artifactCopies);

            // Always use the group name directly
            $mergedArtifact->name = $groupName;
            $mergedArtifact->meta = array_merge($mergedArtifact->meta ?? [], [
                'group_name'  => $groupName,
                'description' => $description,
                'file_count'  => count($pageNumbers),
            ]);
            $mergedArtifact->save();

            $outputArtifacts[] = $mergedArtifact;

            static::logDebug("Created merged artifact for group '$groupName' with " . count($pageNumbers) . ' files');
        }

        return $outputArtifacts;
    }

    /**
     * Apply duplicate group resolution decisions to merge groups.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $resolutionData  LLM response data
     */
    public function applyDuplicateGroupResolution(TaskRun $taskRun, array $resolutionData): void
    {
        static::logDebug('Applying group deduplication decisions');

        $groupDecisions = $resolutionData['group_decisions'] ?? [];

        if (empty($groupDecisions)) {
            static::logDebug('No group decisions to apply');

            return;
        }

        // Get the merge process and its artifacts
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', 'Merge')
            ->first();

        if (!$mergeProcess) {
            static::logDebug('No merge process found');

            return;
        }

        $mergedArtifacts = $mergeProcess->outputArtifacts;

        if ($mergedArtifacts->isEmpty()) {
            static::logDebug('No merged artifacts found');

            return;
        }

        $mergesAndRenames = $this->buildGroupMergePlan($groupDecisions);

        if (empty($mergesAndRenames['merges']) && empty($mergesAndRenames['renames'])) {
            static::logDebug('No groups to merge or rename after processing decisions');

            return;
        }

        // Apply renames first (single group name corrections)
        foreach ($mergesAndRenames['renames'] as $oldName => $newName) {
            $this->renameGroup($oldName, $newName, $mergedArtifacts);
        }

        // Then apply merges (multiple groups into one)
        foreach ($mergesAndRenames['merges'] as $targetGroup => $sourceGroups) {
            foreach ($sourceGroups as $sourceGroup) {
                $this->mergeGroups($sourceGroup, $targetGroup, $mergedArtifacts);
            }
        }

        static::logDebug('Group deduplication application complete');
    }

    /**
     * Build a plan for merging and renaming groups from decisions.
     *
     * @param  array  $groupDecisions  Group decisions from LLM
     * @return array Map with 'merges' (target => [sources]) and 'renames' (old => new)
     */
    protected function buildGroupMergePlan(array $groupDecisions): array
    {
        $merges  = [];
        $renames = [];

        foreach ($groupDecisions as $decision) {
            $originalNames = $decision['original_names'] ?? [];
            $canonicalName = $decision['canonical_name'] ?? '';
            $reason        = $decision['reason']         ?? '';

            if (empty($originalNames) || !$canonicalName) {
                static::logDebug('Decision missing original_names or canonical_name - skipping');

                continue;
            }

            // If only one original name, this is either a rename or no change
            if (count($originalNames) === 1) {
                $originalName = $originalNames[0];

                if ($originalName !== $canonicalName) {
                    // This is a rename (spelling correction)
                    static::logDebug("Renaming '$originalName' to '$canonicalName'. Reason: $reason");
                    $renames[$originalName] = $canonicalName;
                } else {
                    // No change needed
                    static::logDebug("Group '$canonicalName' needs no changes");
                }
            } else {
                // Multiple original names - this is a merge
                static::logDebug('Merging ' . count($originalNames) . " groups into '$canonicalName'. Reason: $reason");

                // Find which original name matches the canonical name (if any)
                $targetExists = in_array($canonicalName, $originalNames);

                if ($targetExists) {
                    // Canonical name is one of the originals - merge others into it
                    foreach ($originalNames as $originalName) {
                        if ($originalName !== $canonicalName) {
                            if (!isset($merges[$canonicalName])) {
                                $merges[$canonicalName] = [];
                            }
                            $merges[$canonicalName][] = $originalName;
                            static::logDebug("  - Merging '$originalName' into '$canonicalName'");
                        }
                    }
                } else {
                    // Canonical name is new (corrected spelling) - merge all originals, then rename the first
                    $firstOriginal = $originalNames[0];

                    // Rename first original to canonical
                    $renames[$firstOriginal] = $canonicalName;
                    static::logDebug("  - Renaming '$firstOriginal' to '$canonicalName'");

                    // Merge remaining originals into the renamed one
                    for ($i = 1; $i < count($originalNames); $i++) {
                        if (!isset($merges[$canonicalName])) {
                            $merges[$canonicalName] = [];
                        }
                        $merges[$canonicalName][] = $originalNames[$i];
                        static::logDebug("  - Merging '{$originalNames[$i]}' into '$canonicalName'");
                    }
                }
            }
        }

        return [
            'merges'  => $merges,
            'renames' => $renames,
        ];
    }

    /**
     * Rename a group (spelling correction).
     *
     * @param  string  $oldName  Old group name
     * @param  string  $newName  New group name
     * @param  Collection  $mergedArtifacts  Merged artifacts collection
     */
    protected function renameGroup(string $oldName, string $newName, Collection $mergedArtifacts): void
    {
        // Find artifact with the old name
        $artifact = null;

        foreach ($mergedArtifacts as $a) {
            $meta = $a->meta ?? [];
            if (($meta['group_name'] ?? '') === $oldName) {
                $artifact = $a;
                break;
            }
        }

        if (!$artifact) {
            static::logDebug("Could not find artifact for group '$oldName' - skipping rename");

            return;
        }

        static::logDebug("Renaming group '$oldName' to '$newName'");

        // Update artifact metadata and name
        $artifact->meta = array_merge($artifact->meta ?? [], [
            'group_name' => $newName,
        ]);
        $artifact->name = $newName;
        $artifact->save();

        static::logDebug("Successfully renamed group '$oldName' to '$newName'");
    }

    /**
     * Merge one group into another.
     *
     * @param  string  $sourceGroup  Source group name
     * @param  string  $targetGroup  Target group name
     * @param  Collection  $mergedArtifacts  Merged artifacts collection
     */
    protected function mergeGroups(string $sourceGroup, string $targetGroup, Collection $mergedArtifacts): void
    {
        // Find source and target artifacts
        $sourceArtifact = null;
        $targetArtifact = null;

        foreach ($mergedArtifacts as $artifact) {
            $meta = $artifact->meta ?? [];
            if (($meta['group_name'] ?? '') === $sourceGroup) {
                $sourceArtifact = $artifact;
            }
            if (($meta['group_name'] ?? '') === $targetGroup) {
                $targetArtifact = $artifact;
            }
        }

        if (!$sourceArtifact || !$targetArtifact) {
            static::logDebug("Could not find artifacts for '$sourceGroup' or '$targetGroup' - skipping merge");

            return;
        }

        // Get stored files from both artifacts
        $sourceFiles = $sourceArtifact->storedFiles ?? collect();
        $targetFiles = $targetArtifact->storedFiles ?? collect();

        static::logDebug("Merging {$sourceFiles->count()} files from '$sourceGroup' into '$targetGroup' ({$targetFiles->count()} existing files)");

        // Attach source files to target artifact
        foreach ($sourceFiles as $file) {
            // Check if file is already attached to avoid duplicates
            $alreadyAttached = $targetArtifact->storedFiles()
                ->where('stored_files.id', $file->id)
                ->exists();

            if (!$alreadyAttached) {
                $targetArtifact->storedFiles()->attach($file->id, [
                    'category' => 'input',
                ]);
            }
        }

        // No need to update relation counter - storedFiles don't have a counter column
        // The relationship is managed through the pivot table only

        // Refresh the relationship to get accurate count after attaching files
        $targetArtifact->load('storedFiles');
        $mergedFileCount = $targetArtifact->storedFiles->count();

        // Update target artifact name to include merged file count
        $targetArtifact->name = "$targetGroup ($mergedFileCount files)";
        $targetArtifact->save();

        // Delete source artifact (it's been merged)
        $sourceArtifact->delete();

        static::logDebug("Successfully merged '$sourceGroup' into '$targetGroup' - total files now: $mergedFileCount");
    }

    /**
     * Store adjacency data (belongs_to_previous) on StoredFiles for use by other tasks.
     *
     * This persists the file organization results on the StoredFile's meta field so that
     * downstream tasks (like ExtractData) can use adjacency signals to determine document
     * boundaries without re-running the file organization analysis.
     *
     * @param  TaskRun  $taskRun  The task run containing input artifacts
     * @param  array  $fileToGroupMapping  Mapping from page_number to file data including adjacency scores
     */
    protected function storeAdjacencyDataOnStoredFiles(TaskRun $taskRun, array $fileToGroupMapping): void
    {
        if (empty($fileToGroupMapping)) {
            static::logDebug('No file mapping data to store on StoredFiles');

            return;
        }

        // Get all unique original_stored_file_ids from the input artifacts' stored files
        // Use reorder() to clear the default ordering from inputArtifacts() which conflicts with DISTINCT
        $originalFileIds = $taskRun->inputArtifacts()
            ->reorder()
            ->join('stored_file_storables', function ($join) {
                $join->on('artifacts.id', '=', 'stored_file_storables.storable_id')
                    ->where('stored_file_storables.storable_type', '=', Artifact::class);
            })
            ->join('stored_files', 'stored_file_storables.stored_file_id', '=', 'stored_files.id')
            ->select('stored_files.original_stored_file_id')
            ->distinct()
            ->pluck('original_stored_file_id')
            ->filter()
            ->toArray();

        if (empty($originalFileIds)) {
            static::logDebug('No original stored file IDs found in input artifacts');

            return;
        }

        static::logDebug('Storing adjacency data for ' . count($fileToGroupMapping) . ' pages across ' . count($originalFileIds) . ' original files');

        $updatedCount = 0;

        foreach ($fileToGroupMapping as $pageNumber => $fileData) {
            // Find the StoredFile by page_number and original_stored_file_id
            $storedFile = StoredFile::where('page_number', $pageNumber)
                ->whereIn('original_stored_file_id', $originalFileIds)
                ->first();

            if (!$storedFile) {
                static::logDebug("StoredFile not found for page $pageNumber");

                continue;
            }

            // Update meta with adjacency data
            // ALWAYS set belongs_to_previous key (even if null) so downstream tasks can check
            // if the key EXISTS to determine whether File Organization has been run
            $storedFile->meta = array_merge($storedFile->meta ?? [], [
                'belongs_to_previous'        => $fileData['belongs_to_previous']        ?? null,
                'belongs_to_previous_reason' => $fileData['belongs_to_previous_reason'] ?? null,
                'file_organization_group'    => $fileData['group_name']                 ?? null,
            ]);
            $storedFile->save();
            $updatedCount++;
        }

        static::logDebug("Updated adjacency data on $updatedCount StoredFiles");
    }
}
