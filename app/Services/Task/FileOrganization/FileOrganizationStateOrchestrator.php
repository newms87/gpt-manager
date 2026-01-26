<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use App\Services\Task\TaskProcessDispatcherService;
use App\Services\Task\TranscodePrerequisiteService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

/**
 * Unified state machine orchestrator for FileOrganizationTaskRunner.
 * Handles all phase transitions: page resolution, transcoding, windows, merge, and resolution.
 *
 * Both runInitializeOperation() and afterAllProcessesCompleted() call advanceToNextPhase()
 * to determine and create the next set of processes based on current state.
 */
class FileOrganizationStateOrchestrator
{
    use HasDebugLogging;

    /**
     * Advance the task run to its next phase based on current state.
     * Creates appropriate processes for the next phase.
     */
    public function advanceToNextPhase(TaskRun $taskRun): void
    {
        // Phase 1: Resolve pages from input artifacts
        if ($this->needsPageResolution($taskRun)) {
            $this->resolvePages($taskRun);
            // Synchronous - falls through to next check
        }

        // Phase 2: Transcode resolved pages that need LLM text transcode
        if ($this->needsTranscoding($taskRun)) {
            $this->createTranscodeProcesses($taskRun);
            TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

            return;
        }

        // Phase 3: Create comparison window processes
        if ($this->needsWindows($taskRun)) {
            $this->createWindowProcesses($taskRun);
            TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

            return;
        }

        // Phase 4: Create merge process once all windows complete
        if ($this->needsMerge($taskRun)) {
            $this->createMergeProcessIfReady($taskRun);

            return;
        }

        // Phase 5: Create resolution processes after merge completes
        if ($this->needsResolution($taskRun)) {
            $this->createResolutionProcesses($taskRun);

            return;
        }

        static::logDebug('All phases complete', ['task_run_id' => $taskRun->id]);
    }

    /**
     * Check if page resolution is needed.
     * Returns true if no "Resolved Pages" input artifact exists yet.
     */
    protected function needsPageResolution(TaskRun $taskRun): bool
    {
        return !$taskRun->inputArtifacts()
            ->where('name', 'Resolved Pages')
            ->exists();
    }

    /**
     * Check if transcoding phase is needed.
     * Returns true if transcode processes exist but are incomplete,
     * or if resolved pages need LLM text transcode.
     */
    protected function needsTranscoding(TaskRun $taskRun): bool
    {
        // Check if transcode processes exist but aren't complete
        $transcodeProcesses = $taskRun->taskProcesses()
            ->where('operation', TranscodePrerequisiteService::OPERATION_TRANSCODE)
            ->get();

        if ($transcodeProcesses->isNotEmpty()) {
            return $transcodeProcesses->whereNull('completed_at')->isNotEmpty();
        }

        // Check if any resolved pages need LLM text transcode
        $resolvedPagesArtifact = $taskRun->inputArtifacts()
            ->where('name', 'Resolved Pages')
            ->first();

        if (!$resolvedPagesArtifact) {
            return false;
        }

        $pages = $resolvedPagesArtifact->storedFiles;

        if ($pages->isEmpty()) {
            return false;
        }

        // Create temporary artifacts to check, then clean them all up
        $tempArtifacts = $this->createTemporaryPageArtifacts($taskRun, $pages);
        $needsTranscode = app(TranscodePrerequisiteService::class)->getArtifactsNeedingTranscode($tempArtifacts);
        $hasNeedsTranscode = $needsTranscode->isNotEmpty();

        // Clean up all temp artifacts - createTranscodeProcesses will recreate as needed
        foreach ($tempArtifacts as $artifact) {
            $artifact->storedFiles()->detach();
            $artifact->delete();
        }

        return $hasNeedsTranscode;
    }

    /**
     * Check if window processes are needed.
     * Returns true if no window processes exist yet and pages are resolved.
     */
    protected function needsWindows(TaskRun $taskRun): bool
    {
        $hasWindowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->exists();

        if ($hasWindowProcesses) {
            return false;
        }

        // Pages must be resolved before creating windows
        return $taskRun->inputArtifacts()
            ->where('name', 'Resolved Pages')
            ->exists();
    }

    /**
     * Check if merge phase is needed.
     * Returns true if all windows are complete and no merge process exists.
     */
    protected function needsMerge(TaskRun $taskRun): bool
    {
        $hasMergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->exists();

        if ($hasMergeProcess) {
            return false;
        }

        // Windows must exist and all be completed
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        if ($windowProcesses->isEmpty()) {
            return false;
        }

        return $windowProcesses->every(fn($process) => $process->completed_at !== null);
    }

    /**
     * Check if resolution phase is needed.
     * Returns true after merge completes and duplicate group resolution process hasn't been created.
     */
    protected function needsResolution(TaskRun $taskRun): bool
    {
        // Merge must be complete
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        if (!$mergeProcess || $mergeProcess->completed_at === null) {
            return false;
        }

        // Check if groups_for_deduplication exists and duplicate resolution process hasn't been created
        $meta = $mergeProcess->meta ?? [];

        if (!empty($meta['groups_for_deduplication'])) {
            return !$taskRun->taskProcesses()
                ->where('operation', FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION)
                ->exists();
        }

        return false;
    }

    /**
     * Phase 1: Resolve pages from input artifacts.
     * This is synchronous - does not create processes.
     */
    protected function resolvePages(TaskRun $taskRun): void
    {
        static::logDebug('Resolving pages');
        app(PageResolutionService::class)->resolvePages($taskRun);
    }

    /**
     * Phase 2: Create transcode processes for pages needing LLM text transcode.
     */
    protected function createTranscodeProcesses(TaskRun $taskRun): void
    {
        static::logDebug('Creating transcode processes for resolved pages');

        $resolvedPagesArtifact = $taskRun->inputArtifacts()
            ->where('name', 'Resolved Pages')
            ->first();

        if (!$resolvedPagesArtifact) {
            return;
        }

        $pages = $resolvedPagesArtifact->storedFiles;

        // Create temporary artifacts wrapping each page for transcode service
        $tempArtifacts = $this->createTemporaryPageArtifacts($taskRun, $pages);
        $transcodeService = app(TranscodePrerequisiteService::class);
        $needsTranscode = $transcodeService->getArtifactsNeedingTranscode($tempArtifacts);

        // Clean up temp artifacts that don't need transcode
        $artifactsNotNeeded = $tempArtifacts->diff($needsTranscode);
        foreach ($artifactsNotNeeded as $artifact) {
            $artifact->storedFiles()->detach();
            $artifact->delete();
        }

        if ($needsTranscode->isEmpty()) {
            return;
        }

        // Create processes for artifacts that need transcode (they remain as input artifacts)
        $transcodeService->createTranscodeProcesses($taskRun, $needsTranscode);
        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug('Created transcode processes', ['count' => $needsTranscode->count()]);
    }

    /**
     * Create temporary artifacts wrapping each page StoredFile for transcode checking.
     */
    protected function createTemporaryPageArtifacts(TaskRun $taskRun, $pages): Collection
    {
        $artifacts = collect();

        foreach ($pages as $storedFile) {
            $artifact = Artifact::create([
                'team_id' => $taskRun->taskDefinition->team_id,
                'name'    => 'Transcode: Page ' . $storedFile->page_number,
            ]);
            $artifact->storedFiles()->attach($storedFile->id);
            $artifacts->push($artifact);
        }

        return $artifacts;
    }

    /**
     * Phase 3: Create comparison window processes from resolved pages.
     */
    protected function createWindowProcesses(TaskRun $taskRun): void
    {
        static::logDebug('Creating window processes');

        $config = $taskRun->taskDefinition->task_runner_config ?? [];
        $windowSize = $config['comparison_window_size'] ?? FileOrganizationTaskRunner::DEFAULT_COMPARISON_WINDOW_SIZE;
        $overlap = $config['comparison_window_overlap'] ?? FileOrganizationTaskRunner::DEFAULT_COMPARISON_WINDOW_OVERLAP;

        // Get resolved pages
        $resolvedPagesArtifact = $taskRun->inputArtifacts()
            ->where('name', 'Resolved Pages')
            ->first();

        $pages = $resolvedPagesArtifact?->storedFiles ?? collect();

        app(WindowProcessService::class)->createWindowProcesses($taskRun, $windowSize, $overlap, $pages);

        static::logDebug('Window processes created');
    }

    /**
     * Phase 4: Create merge process if all windows are completed.
     *
     * @return bool True if merge process was created
     */
    public function createMergeProcessIfReady(TaskRun $taskRun): bool
    {
        // Check if a merge process already exists
        $hasMergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->exists();

        if ($hasMergeProcess) {
            static::logDebug('Merge process already exists or completed');

            return false;
        }

        // Check if window processes exist and have completed
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        if ($windowProcesses->isEmpty()) {
            static::logDebug('No window processes found - skipping merge process creation');

            return false;
        }

        // Check if all window processes are completed
        $allCompleted = $windowProcesses->every(fn($process) => $process->completed_at !== null);

        if (!$allCompleted) {
            static::logDebug('Window processes still running - skipping merge process creation');

            return false;
        }

        static::logDebug('Creating merge process');

        // Create the merge process
        $taskRun->taskProcesses()->create([
            'name'      => 'Merge Window Results',
            'operation' => FileOrganizationTaskRunner::OPERATION_MERGE,
            'activity'  => 'Merging window comparison results into final groups',
            'meta'      => [],
            'is_ready'  => true,
        ]);

        $taskRun->updateRelationCounter('taskProcesses');

        // Dispatch the merge process
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        return true;
    }

    /**
     * Phase 5: Create resolution processes based on merge process metadata.
     * Only creates duplicate group resolution process (low confidence and null group resolution are dead code paths).
     */
    public function createResolutionProcesses(TaskRun $taskRun): void
    {
        // Check if duplicate group resolution process already exists
        $hasDuplicateGroupResolution = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION)
            ->exists();

        if ($hasDuplicateGroupResolution) {
            static::logDebug('Duplicate group resolution process already exists');

            return;
        }

        // Check if merge process has groups to deduplicate
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        if (!$mergeProcess) {
            static::logDebug('No merge process found');

            return;
        }

        $this->createDuplicateGroupResolutionProcess($taskRun, $mergeProcess);
    }

    /**
     * Create duplicate group resolution process.
     * This always runs to deduplicate and correct group names across all groups.
     */
    protected function createDuplicateGroupResolutionProcess(TaskRun $taskRun, TaskProcess $mergeProcess): void
    {
        if (!isset($mergeProcess->meta['groups_for_deduplication'])) {
            static::logDebug('No groups_for_deduplication metadata found');

            return;
        }

        $groupsForDeduplication = $mergeProcess->meta['groups_for_deduplication'];

        if (empty($groupsForDeduplication)) {
            static::logDebug('No groups to deduplicate');

            return;
        }

        static::logDebug('Creating group deduplication process for ' . count($groupsForDeduplication) . ' groups');

        // Create the resolution process (no specific artifacts needed, this is group-level)
        $taskRun->taskProcesses()->create([
            'name'      => 'Deduplicate Group Names',
            'operation' => FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION,
            'activity'  => 'Reviewing all group names for spelling corrections and merges',
            'meta'      => [
                'groups_for_deduplication' => $groupsForDeduplication,
            ],
            'is_ready'  => true,
        ]);

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug('Created group deduplication process');

        // Dispatch the resolution process
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);
    }
}
