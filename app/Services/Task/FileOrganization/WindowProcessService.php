<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use Newms87\Danx\Traits\HasDebugLogging;
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
     * @param  Collection|null  $pages  Optional collection of StoredFile objects (new pattern: all pages on single artifact)
     */
    public function createWindowProcesses(TaskRun $taskRun, int $windowSize, int $overlap = 1, ?Collection $pages = null): void
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

        $inputArtifacts = collect();

        if ($pages) {
            // New pattern: pages are StoredFiles on a single artifact
            static::logDebug('Using pages collection with ' . $pages->count() . ' stored files');
            $files = $this->getFileListFromPages($pages);
        } else {
            // Old pattern: one file per artifact
            $inputArtifacts = $taskRun->inputArtifacts()
                ->orderBy('position')
                ->get();

            static::logDebug('Found ' . $inputArtifacts->count() . ' input artifacts');

            if ($inputArtifacts->isEmpty()) {
                static::logDebug('No input artifacts, skipping window creation');

                return;
            }

            $files = $this->getFileListFromArtifacts($inputArtifacts);
        }

        // Create overlapping windows with configured overlap
        $windows = $this->createOverlappingWindows($files, $windowSize, $overlap);

        static::logDebug('Created ' . count($windows) . ' comparison windows');

        if (empty($windows)) {
            static::logDebug('No windows created, skipping');

            return;
        }

        // Create a TaskProcess for each window
        foreach ($windows as $window) {
            $this->createSingleWindowProcess($taskRun, $window, $inputArtifacts, $pages);
        }

        $taskRun->updateRelationCounter('taskProcesses');

        static::logDebug('Window creation completed');
    }

    /**
     * Create a single window process.
     *
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $window  Window data from createOverlappingWindows
     * @param  Collection  $inputArtifacts  All input artifacts (used for old pattern)
     * @param  Collection|null  $pages  Optional collection of StoredFile objects (new pattern)
     */
    protected function createSingleWindowProcess(TaskRun $taskRun, array $window, Collection $inputArtifacts, ?Collection $pages = null): void
    {
        $windowFiles = $window['files'];

        // Create the process directly (not using TaskProcessRunnerService::prepare to avoid agent setup)
        $taskProcess = $taskRun->taskProcesses()->create([
            'name'      => "Compare Files {$window['window_start']}-{$window['window_end']}",
            'operation' => 'Comparison Window',
            'activity'  => 'Comparing adjacent files in window',
            'meta'      => [],
            'is_ready'  => true, // Ready to run immediately
        ]);

        if ($pages) {
            // New pattern: create a window pages artifact containing the window's StoredFiles
            $windowPagesArtifact = Artifact::create([
                'team_id' => $taskRun->taskDefinition->team_id,
                'name'    => "Window Pages {$window['window_start']}-{$window['window_end']}",
            ]);

            // Get the StoredFiles for this window by matching file_id (stored_file_id in new pattern)
            $windowFileIds     = array_column($windowFiles, 'file_id');
            $windowStoredFiles = $pages->whereIn('id', $windowFileIds);

            // Attach stored files to the window pages artifact
            foreach ($windowStoredFiles as $storedFile) {
                $windowPagesArtifact->storedFiles()->attach($storedFile->id);
            }

            // Attach window pages artifact to the process
            $taskProcess->inputArtifacts()->attach($windowPagesArtifact->id, ['category' => 'input']);
        } else {
            // Old pattern: attach input artifacts by ID
            $windowFileIds   = array_column($windowFiles, 'file_id');
            $windowArtifacts = $inputArtifacts->whereIn('id', $windowFileIds);

            foreach ($windowArtifacts as $artifact) {
                $taskProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            }
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

    /**
     * Get file IDs from artifacts with page_number from StoredFile.
     * Used to create the initial list of files for window creation.
     *
     * @param  Collection  $artifacts  Collection of artifacts
     * @return array Array of ['file_id' => artifact_id, 'page_number' => int]
     */
    protected function getFileListFromArtifacts(Collection $artifacts): array
    {
        $files = [];

        foreach ($artifacts as $artifact) {
            // Get page_number from the StoredFile model (NOT from artifact meta or position)
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? $artifact->position ?? 0;

            $files[] = [
                'file_id'     => $artifact->id,
                'page_number' => $pageNumber,
            ];
        }

        // Sort by page_number
        usort($files, fn($a, $b) => $a['page_number'] <=> $b['page_number']);

        static::logDebug('Extracted ' . count($files) . ' files from artifacts');

        return $files;
    }

    /**
     * Get file list from StoredFile pages collection.
     * Maps each StoredFile to file_id and page_number, sorted by page_number.
     *
     * @param  Collection  $pages  Collection of StoredFile objects
     * @return array Array of ['file_id' => stored_file_id, 'page_number' => int]
     */
    protected function getFileListFromPages(Collection $pages): array
    {
        $files = $pages->map(fn($storedFile) => [
            'file_id'     => $storedFile->id,
            'page_number' => $storedFile->page_number,
        ])->sortBy('page_number')->values()->toArray();

        static::logDebug('Extracted ' . count($files) . ' files from pages collection');

        return $files;
    }

    /**
     * Create overlapping windows from a list of files.
     * Windows overlap by the specified number of files.
     *
     * Examples with different overlaps:
     * - 10 files, size 5, overlap 1 → windows: [1-5], [5-9], [9-10]
     * - 10 files, size 5, overlap 2 → windows: [1-5], [4-8], [7-10]
     * - 10 files, size 5, overlap 3 → windows: [1-5], [3-7], [5-9], [7-10]
     * - 6 files, size 5, overlap 1 → windows: [1-5], [5-6]
     * - 4 files, size 5, overlap 1 → window: [1-4]
     *
     * @param  array  $files  Array of ['file_id' => id, 'page_number' => int]
     * @param  int  $windowSize  Maximum number of files per window
     * @param  int  $windowOverlap  Number of files to overlap between windows (default: 1)
     * @return array Array of windows with metadata
     */
    protected function createOverlappingWindows(array $files, int $windowSize, int $windowOverlap = 1): array
    {
        if (empty($files)) {
            static::logDebug('No files to create windows from');

            return [];
        }

        $windows   = [];
        $fileCount = count($files);

        static::logDebug("Creating overlapping windows from $fileCount files with max window size $windowSize and overlap $windowOverlap");

        // Create overlapping windows with configurable overlap
        $windowIndex = 0;
        $startIndex  = 0;

        while ($startIndex < $fileCount) {
            $windowFiles = [];
            $pageNumbers = [];

            // Collect up to $windowSize files for this window
            for ($j = 0; $j < $windowSize && ($startIndex + $j) < $fileCount; $j++) {
                $file          = $files[$startIndex + $j];
                $windowFiles[] = $file;
                $pageNumbers[] = $file['page_number'];
            }

            // Only create window if we have at least 2 files
            if (count($windowFiles) < 2) {
                static::logDebug("Window $windowIndex: skipping (only " . count($windowFiles) . ' file(s))');
                break;
            }

            $windows[] = [
                'window_index' => $windowIndex,
                'window_start' => min($pageNumbers),
                'window_end'   => max($pageNumbers),
                'files'        => $windowFiles,
            ];

            static::logDebug("Window $windowIndex: page numbers " . min($pageNumbers) . '-' . max($pageNumbers) . ' (' . count($windowFiles) . ' files)');
            $windowIndex++;

            // Move to next window with configured overlap
            $startIndex += $windowSize - $windowOverlap;
        }

        static::logDebug('Created ' . count($windows) . ' overlapping windows');

        return $windows;
    }
}
