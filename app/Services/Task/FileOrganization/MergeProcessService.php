<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\ArtifactsMergeService;
use App\Services\Task\FileOrganizationMergeService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

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

        // Merge the windows
        $mergeService         = app(FileOrganizationMergeService::class);
        $mergeResult          = $mergeService->mergeWindowResults($windowArtifacts);
        $finalGroups          = $mergeResult['groups'];
        $fileToGroup          = $mergeResult['file_to_group_mapping'];
        $nullGroupsNeedingLlm = $mergeResult['null_groups_needing_llm'] ?? [];

        static::logDebug('Merge completed: ' . count($finalGroups) . ' final groups');

        if (!empty($nullGroupsNeedingLlm)) {
            static::logDebug('Found ' . count($nullGroupsNeedingLlm) . ' null group files that need LLM resolution');
        }

        // Check for low-confidence files
        $lowConfidenceFiles = $mergeService->identifyLowConfidenceFiles($fileToGroup);

        // Check for duplicate groups
        $duplicateDetector   = app(DuplicateGroupDetector::class);
        $duplicateCandidates = $duplicateDetector->identifyDuplicateCandidates($finalGroups);

        // Store issues that need resolution in metadata
        $metadata = $this->buildResolutionMetadata(
            $lowConfidenceFiles,
            $nullGroupsNeedingLlm,
            $duplicateCandidates,
            $duplicateDetector,
            $finalGroups,
            $fileToGroup
        );

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
     * @param  array  $lowConfidenceFiles  Low confidence files
     * @param  array  $nullGroupsNeedingLlm  Null group files
     * @param  array  $duplicateCandidates  Duplicate group candidates
     * @param  DuplicateGroupDetector  $duplicateDetector  Duplicate detector instance
     * @param  array  $finalGroups  Final groups
     * @param  array  $fileToGroup  File to group mapping
     * @return array Metadata for task process
     */
    protected function buildResolutionMetadata(
        array $lowConfidenceFiles,
        array $nullGroupsNeedingLlm,
        array $duplicateCandidates,
        DuplicateGroupDetector $duplicateDetector,
        array $finalGroups,
        array $fileToGroup
    ): array {
        $metadata = [];

        if (!empty($lowConfidenceFiles)) {
            static::logDebug('Found ' . count($lowConfidenceFiles) . ' low-confidence files');
            $metadata['low_confidence_files'] = $lowConfidenceFiles;
        }

        if (!empty($nullGroupsNeedingLlm)) {
            static::logDebug('Found ' . count($nullGroupsNeedingLlm) . ' null group files needing LLM resolution');
            $metadata['null_groups_needing_llm'] = $nullGroupsNeedingLlm;
        }

        if (!empty($duplicateCandidates)) {
            static::logDebug('Found ' . count($duplicateCandidates) . ' duplicate group candidates');
            // Prepare duplicate candidates with full context for LLM resolution
            $duplicatesWithContext = [];
            foreach ($duplicateCandidates as $candidate) {
                $duplicatesWithContext[] = $duplicateDetector->prepareDuplicateForResolution($candidate, $finalGroups, $fileToGroup);
            }
            $metadata['duplicate_group_candidates'] = $duplicatesWithContext;
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
            $fileIds     = $group['files'];

            // Get the artifacts for this group
            $groupArtifacts = $taskRun->inputArtifacts()
                ->whereIn('artifacts.id', $fileIds)
                ->orderBy('artifacts.position')
                ->get();

            if ($groupArtifacts->isEmpty()) {
                static::logDebug("Group '$groupName': no artifacts found, skipping");

                continue;
            }

            // Find the window artifact that identified this group
            $windowArtifactName = $this->findWindowArtifactName($groupName, $windowArtifacts);

            // Create copies of input artifacts to preserve originals
            $artifactCopies = [];
            foreach ($groupArtifacts as $artifact) {
                $artifactCopies[] = $artifact->copy();
            }

            // Create merged artifact for this group
            $mergedArtifact = app(ArtifactsMergeService::class)->merge($artifactCopies);

            // Use the window artifact's name if available, otherwise use a generic name
            $mergedArtifact->name = $windowArtifactName ?? "Group: $groupName";
            $mergedArtifact->meta = array_merge($mergedArtifact->meta ?? [], [
                'group_name'  => $groupName,
                'description' => $description,
                'file_count'  => count($fileIds),
            ]);
            $mergedArtifact->save();

            $outputArtifacts[] = $mergedArtifact;

            static::logDebug("Created merged artifact for group '$groupName' with " . count($fileIds) . ' files');
        }

        return $outputArtifacts;
    }

    /**
     * Find the window artifact name that first identified this group.
     *
     * @param  string  $groupName  Group name to find
     * @param  Collection  $windowArtifacts  Window artifacts
     * @return string|null Window artifact name or null
     */
    protected function findWindowArtifactName(string $groupName, Collection $windowArtifacts): ?string
    {
        foreach ($windowArtifacts as $windowArtifact) {
            $groups = $windowArtifact->json_content['groups'] ?? [];
            foreach ($groups as $windowGroup) {
                if (($windowGroup['name'] ?? null) === $groupName) {
                    return $windowArtifact->name;
                }
            }
        }

        return null;
    }

    /**
     * Apply duplicate group resolution decisions to merge groups.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $resolutionData  LLM response data
     */
    public function applyDuplicateGroupResolution(TaskRun $taskRun, array $resolutionData): void
    {
        static::logDebug('Applying duplicate group resolution decisions');

        $decisions = $resolutionData['decisions'] ?? [];

        if (empty($decisions)) {
            static::logDebug('No decisions to apply');

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

        $groupsToMerge = $this->buildGroupMergePlan($decisions);

        if (empty($groupsToMerge)) {
            static::logDebug('No groups to merge after processing decisions');

            return;
        }

        // Apply the merges
        foreach ($groupsToMerge as $sourceGroup => $targetGroup) {
            $this->mergeGroups($sourceGroup, $targetGroup, $mergedArtifacts);
        }

        static::logDebug('Duplicate group resolution application complete');
    }

    /**
     * Build a plan for merging groups from decisions.
     *
     * @param  array  $decisions  Resolution decisions
     * @return array Map of source_group => target_group
     */
    protected function buildGroupMergePlan(array $decisions): array
    {
        $groupsToMerge = [];

        foreach ($decisions as $decision) {
            $areDuplicates  = $decision['are_duplicates']  ?? false;
            $group1Name     = $decision['group1_name']     ?? '';
            $group2Name     = $decision['group2_name']     ?? '';
            $canonicalGroup = $decision['canonical_group'] ?? '';
            $confidence     = $decision['confidence']      ?? 0;
            $reason         = $decision['reason']          ?? '';

            if (!$areDuplicates) {
                static::logDebug("Groups '$group1Name' and '$group2Name' are NOT duplicates - keeping separate. Reason: $reason");

                continue;
            }

            if (!$canonicalGroup) {
                static::logDebug('Decision marked as duplicates but no canonical group specified - skipping');

                continue;
            }

            // Determine which group to merge into which
            $sourceGroup = ($canonicalGroup === $group1Name) ? $group2Name : $group1Name;
            $targetGroup = $canonicalGroup;

            static::logDebug("Merging '$sourceGroup' INTO '$targetGroup' (confidence: $confidence). Reason: $reason");

            $groupsToMerge[$sourceGroup] = $targetGroup;
        }

        return $groupsToMerge;
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

        $targetArtifact->updateRelationCounter('storedFiles');

        // Update target artifact name to reflect merged content
        $mergedFileCount      = $targetArtifact->storedFiles->count();
        $targetArtifact->name = "$targetGroup ($mergedFileCount files)";
        $targetArtifact->save();

        // Delete source artifact (it's been merged)
        $sourceArtifact->delete();

        static::logDebug("Successfully merged '$sourceGroup' into '$targetGroup' - total files now: $mergedFileCount");
    }
}
