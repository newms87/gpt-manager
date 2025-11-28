<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganizationMergeService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Creates and manages comparison window processes for file organization.
 */
class WindowProcessService
{
    use HasDebugLogging;

    /**
     * Create window processes for comparing adjacent files.
     *
     * @param  TaskRun  $taskRun  The task run to create windows for
     * @param  int  $windowSize  Size of each comparison window
     * @param  int  $overlap  Number of files to overlap between windows (default: 1)
     */
    public function createWindowProcesses(TaskRun $taskRun, int $windowSize, int $overlap = 1): void
    {
        static::logDebug('Creating file organization window processes');

        // Validate window size
        if ($windowSize < 2 || $windowSize > 100) {
            throw new ValidationError('comparison_window_size must be between 2 and 100');
        }

        // Validate overlap
        if ($overlap < 1 || $overlap >= $windowSize) {
            throw new ValidationError("comparison_window_overlap must be >= 1 and < comparison_window_size ($windowSize). Got: $overlap");
        }

        static::logDebug("Using comparison window size: $windowSize, overlap: $overlap");

        // Get all input artifacts
        $inputArtifacts = $taskRun->inputArtifacts()
            ->orderBy('position')
            ->get();

        static::logDebug('Found ' . $inputArtifacts->count() . ' input artifacts');

        if ($inputArtifacts->isEmpty()) {
            static::logDebug('No input artifacts, skipping window creation');

            return;
        }

        // Create file list from artifacts (using page_number from StoredFile)
        $mergeService = app(FileOrganizationMergeService::class);
        $files        = $mergeService->getFileListFromArtifacts($inputArtifacts);

        // Create overlapping windows with configured overlap
        $windows = $mergeService->createOverlappingWindows($files, $windowSize, $overlap);

        static::logDebug('Created ' . count($windows) . ' comparison windows');

        if (empty($windows)) {
            static::logDebug('No windows created, skipping');

            return;
        }

        // Create a TaskProcess for each window
        foreach ($windows as $window) {
            $this->createSingleWindowProcess($taskRun, $window, $inputArtifacts);
        }

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug('Window creation completed');
    }

    /**
     * Create a single window process.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $window  Window data from createOverlappingWindows
     * @param  Collection  $inputArtifacts  All input artifacts
     */
    protected function createSingleWindowProcess(TaskRun $taskRun, array $window, Collection $inputArtifacts): void
    {
        $windowFiles   = $window['files'];
        $windowFileIds = array_column($windowFiles, 'file_id');

        // Get artifacts for this window
        $windowArtifacts = $inputArtifacts->whereIn('id', $windowFileIds);

        // Create the process directly (not using TaskProcessRunnerService::prepare to avoid agent setup)
        $taskProcess = $taskRun->taskProcesses()->create([
            'name'      => "Compare Files {$window['window_start']}-{$window['window_end']}",
            'operation' => 'Comparison Window',
            'activity'  => 'Comparing adjacent files in window',
            'meta'      => [
                'window_files' => $windowFiles, // Contains page_number and file_id
                'window_start' => $window['window_start'],
                'window_end'   => $window['window_end'],
                'window_index' => $window['window_index'],
            ],
            'is_ready'  => true, // Ready to run immediately
        ]);

        // Attach input artifacts to the window process
        foreach ($windowArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }
        $taskProcess->updateRelationCounter('inputArtifacts');

        static::logDebug("Created window process {$taskProcess->id}: page numbers {$window['window_start']}-{$window['window_end']}");
    }

    /**
     * Complete a window process without adding artifacts to task run output.
     * Window artifacts are intermediate results that should only be attached to the process.
     *
     * @param  TaskProcess  $taskProcess  The window process to complete
     * @param  array  $artifacts  Output artifacts from the window comparison
     */
    public function completeWindowProcess(TaskProcess $taskProcess, array $artifacts): void
    {
        static::logDebug("Window process completed: $taskProcess");

        if ($artifacts) {
            static::logDebug('Attaching window artifacts to process only (not task run): ' . collect($artifacts)->pluck('id')->toJson());

            // Only attach to the process, not the task run
            $artifactIds = collect($artifacts)->pluck('id')->toArray();
            $taskProcess->outputArtifacts()->sync($artifactIds);
            $taskProcess->updateRelationCounter('outputArtifacts');
        }
    }
}
