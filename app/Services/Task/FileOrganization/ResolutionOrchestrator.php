<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\TaskProcessDispatcherService;
use App\Traits\HasDebugLogging;

/**
 * Orchestrates creation of resolution processes after merge completion.
 * Handles low-confidence, null-group, and duplicate-group resolutions.
 */
class ResolutionOrchestrator
{
    use HasDebugLogging;

    /**
     * Create resolution processes based on merge process metadata.
     *
     * @param  TaskRun  $taskRun  The task run
     */
    public function createResolutionProcesses(TaskRun $taskRun): void
    {
        // Check if resolution processes already exist
        $hasLowConfidenceResolution = $taskRun->taskProcesses()
            ->where('operation', 'Low Confidence Resolution')
            ->exists();

        $hasNullGroupResolution = $taskRun->taskProcesses()
            ->where('operation', 'Null Group Resolution')
            ->exists();

        $hasDuplicateGroupResolution = $taskRun->taskProcesses()
            ->where('operation', 'Duplicate Group Resolution')
            ->exists();

        if ($hasLowConfidenceResolution && $hasNullGroupResolution && $hasDuplicateGroupResolution) {
            static::logDebug('All resolution processes already exist or completed');

            return;
        }

        // Check if merge process has issues to resolve
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', 'Merge')
            ->first();

        if (!$mergeProcess) {
            static::logDebug('No merge process found');

            return;
        }

        // Create low-confidence resolution process if needed
        if (!$hasLowConfidenceResolution) {
            $this->createLowConfidenceResolutionProcess($taskRun, $mergeProcess);
        }

        // Create null group resolution process if needed
        if (!$hasNullGroupResolution) {
            $this->createNullGroupResolutionProcess($taskRun, $mergeProcess);
        }

        // Create duplicate group resolution process if needed
        if (!$hasDuplicateGroupResolution) {
            $this->createDuplicateGroupResolutionProcess($taskRun, $mergeProcess);
        }
    }

    /**
     * Create low confidence resolution process if needed.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  TaskProcess  $mergeProcess  The merge process
     */
    protected function createLowConfidenceResolutionProcess(TaskRun $taskRun, TaskProcess $mergeProcess): void
    {
        if (!isset($mergeProcess->meta['low_confidence_files'])) {
            return;
        }

        $lowConfidenceFiles = $mergeProcess->meta['low_confidence_files'];

        if (empty($lowConfidenceFiles)) {
            return;
        }

        static::logDebug('Creating resolution process for ' . count($lowConfidenceFiles) . ' low-confidence files');

        // Get the uncertain files' artifacts
        $uncertainFileIds   = array_column($lowConfidenceFiles, 'file_id');
        $uncertainArtifacts = $taskRun->inputArtifacts()
            ->whereIn('artifacts.id', $uncertainFileIds)
            ->get();

        // Create the resolution process
        $resolutionProcess = $taskRun->taskProcesses()->create([
            'name'      => 'Resolve Low Confidence Files',
            'operation' => 'Low Confidence Resolution',
            'activity'  => 'Reviewing files with uncertain grouping',
            'meta'      => [
                'low_confidence_files' => $lowConfidenceFiles,
            ],
            'is_ready'  => true,
        ]);

        // Attach uncertain files as input artifacts
        foreach ($uncertainArtifacts as $artifact) {
            $resolutionProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }
        $resolutionProcess->updateRelationCounter('inputArtifacts');

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created low confidence resolution process: $resolutionProcess");

        // Dispatch the resolution process
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);
    }

    /**
     * Create null group resolution process if needed.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  TaskProcess  $mergeProcess  The merge process
     */
    protected function createNullGroupResolutionProcess(TaskRun $taskRun, TaskProcess $mergeProcess): void
    {
        if (!isset($mergeProcess->meta['null_groups_needing_llm'])) {
            return;
        }

        $nullGroupFiles = $mergeProcess->meta['null_groups_needing_llm'];

        if (empty($nullGroupFiles)) {
            return;
        }

        static::logDebug('Creating null group resolution process for ' . count($nullGroupFiles) . ' files');

        // Get the null group files' artifacts
        $nullFileIds       = array_column($nullGroupFiles, 'file_id');
        $nullFileArtifacts = $taskRun->inputArtifacts()
            ->whereIn('artifacts.id', $nullFileIds)
            ->get();

        // Create the resolution process
        $nullResolutionProcess = $taskRun->taskProcesses()->create([
            'name'      => 'Resolve Null Group Files',
            'operation' => 'Null Group Resolution',
            'activity'  => 'Determining group assignment for files with no clear identifier',
            'meta'      => [
                'null_groups_needing_llm' => $nullGroupFiles,
            ],
            'is_ready'  => true,
        ]);

        // Attach null group files as input artifacts
        foreach ($nullFileArtifacts as $artifact) {
            $nullResolutionProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }
        $nullResolutionProcess->updateRelationCounter('inputArtifacts');

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created null group resolution process: $nullResolutionProcess");

        // Dispatch the resolution process
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);
    }

    /**
     * Create duplicate group resolution process if needed.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  TaskProcess  $mergeProcess  The merge process
     */
    protected function createDuplicateGroupResolutionProcess(TaskRun $taskRun, TaskProcess $mergeProcess): void
    {
        if (!isset($mergeProcess->meta['duplicate_group_candidates'])) {
            return;
        }

        $duplicateCandidates = $mergeProcess->meta['duplicate_group_candidates'];

        if (empty($duplicateCandidates)) {
            return;
        }

        static::logDebug('Creating duplicate group resolution process for ' . count($duplicateCandidates) . ' duplicate candidates');

        // Create the resolution process (no specific artifacts needed, this is group-level)
        $duplicateResolutionProcess = $taskRun->taskProcesses()->create([
            'name'      => 'Resolve Duplicate Groups',
            'operation' => 'Duplicate Group Resolution',
            'activity'  => 'Determining if similar group names represent the same entity',
            'meta'      => [
                'duplicate_group_candidates' => $duplicateCandidates,
            ],
            'is_ready'  => true,
        ]);

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created duplicate group resolution process: $duplicateResolutionProcess");

        // Dispatch the resolution process
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);
    }

    /**
     * Check if a merge process should be created.
     * Creates merge process if all window processes are completed.
     *
     * @param  TaskRun  $taskRun  The task run
     * @return bool True if merge process was created
     */
    public function createMergeProcessIfReady(TaskRun $taskRun): bool
    {
        // Check if a merge process already exists
        $hasMergeProcess = $taskRun->taskProcesses()
            ->where('operation', 'Merge')
            ->exists();

        if ($hasMergeProcess) {
            static::logDebug('Merge process already exists or completed');

            return false;
        }

        // Check if window processes exist and have completed
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', 'Comparison Window')
            ->get();

        if ($windowProcesses->isEmpty()) {
            static::logDebug('No window processes found - skipping merge process creation');

            return false;
        }

        // Check if all window processes are completed
        $allCompleted = $windowProcesses->every(function ($process) {
            return $process->status === 'Completed';
        });

        if (!$allCompleted) {
            static::logDebug('Window processes still running - skipping merge process creation');

            return false;
        }

        static::logDebug('Creating merge process');

        // Create the merge process
        $mergeProcess = $taskRun->taskProcesses()->create([
            'name'      => 'Merge Window Results',
            'operation' => 'Merge',
            'activity'  => 'Merging window comparison results into final groups',
            'meta'      => [],
            'is_ready'  => true,
        ]);

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created merge process: $mergeProcess");

        // Dispatch the merge process
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        return true;
    }
}
