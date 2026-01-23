<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Services\Task\FileOrganization\AgentThreadService;
use App\Services\Task\FileOrganization\ArtifactResolutionService;
use App\Services\Task\FileOrganization\MergeProcessService;
use App\Services\Task\FileOrganization\PageContextService;
use App\Services\Task\FileOrganization\ResolutionOrchestrator;
use App\Services\Task\FileOrganization\ValidationService;
use App\Services\Task\FileOrganization\WindowConfigArtifactService;
use App\Services\Task\FileOrganization\WindowProcessService;
use App\Services\Task\TaskProcessDispatcherService;
use Newms87\Danx\Exceptions\ValidationError;

class FileOrganizationTaskRunner extends AgentThreadTaskRunner
{
    public const string RUNNER_NAME = 'File Organization';

    public const string OPERATION_COMPARISON_WINDOW = 'Comparison Window',
        OPERATION_MERGE                      = 'Merge',
        OPERATION_LOW_CONFIDENCE_RESOLUTION  = 'Low Confidence Resolution',
        OPERATION_NULL_GROUP_RESOLUTION      = 'Null Group Resolution',
        OPERATION_DUPLICATE_GROUP_RESOLUTION = 'Duplicate Group Resolution';

    public const int DEFAULT_COMPARISON_WINDOW_SIZE = 5,
        DEFAULT_COMPARISON_WINDOW_OVERLAP           = 2;

    public const int DEFAULT_GROUP_CONFIDENCE_THRESHOLD = 3,
        DEFAULT_ADJACENCY_BOUNDARY_THRESHOLD            = 2,
        DEFAULT_MAX_SLIDING_ITERATIONS                  = 3;

    public const string DEFAULT_BLANK_PAGE_HANDLING = 'join_previous';

    public const float DEFAULT_NAME_SIMILARITY_THRESHOLD = 0.7;

    public function run(): void
    {
        match ($this->taskProcess->operation) {
            BaseTaskRunner::OPERATION_DEFAULT       => $this->runInitializeOperation(),
            self::OPERATION_COMPARISON_WINDOW       => $this->runComparisonWindow(),
            self::OPERATION_MERGE                   => $this->runMergeProcess(),
            self::OPERATION_LOW_CONFIDENCE_RESOLUTION   => $this->runLowConfidenceResolution(),
            self::OPERATION_NULL_GROUP_RESOLUTION        => $this->runNullGroupResolution(),
            self::OPERATION_DUPLICATE_GROUP_RESOLUTION   => $this->runDuplicateGroupResolution(),
        };
    }

    /**
     * Run the initialization operation.
     * Creates window processes for comparing adjacent files.
     */
    protected function runInitializeOperation(): void
    {
        static::logDebug('Running initialization - creating window processes');
        $this->createWindowProcesses();
        $this->complete();
    }

    /**
     * Run a comparison window process.
     * Compares adjacent files in the window and groups them.
     */
    protected function runComparisonWindow(): void
    {
        $windowFiles = app(WindowConfigArtifactService::class)->getWindowFiles($this->taskProcess);

        if ($windowFiles === null) {
            throw new ValidationError(
                'Window Config artifact is required for Comparison Window process. ' .
                'The process may have been created before config artifacts were implemented.'
            );
        }

        $inputArtifacts = $this->taskProcess->inputArtifacts;

        // Filter artifacts to only those in the window
        $windowFileIds     = array_column($windowFiles, 'file_id');
        $artifactsInWindow = $inputArtifacts->whereIn('id', $windowFileIds);

        static::logDebug('Window contains ' . $artifactsInWindow->count() . ' artifacts');

        // Setup agent thread
        $agentThread = $this->setupAgentThread($artifactsInWindow);

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from agent thread');
        }

        // Validate that no page appears in multiple groups
        app(ValidationService::class)->validateNoDuplicatePages($artifact->json_content);

        // Store window metadata in the artifact (retrieved from config artifact)
        $windowConfig         = app(WindowConfigArtifactService::class)->getWindowConfigFromProcess($this->taskProcess);
        $meta                 = $artifact->meta ?? [];
        $meta['window_start'] = $windowConfig['window_start'];
        $meta['window_end']   = $windowConfig['window_end'];
        $meta['window_files'] = $windowConfig['window_files'];
        $artifact->meta       = $meta;
        $artifact->save();

        static::logDebug('Window comparison completed successfully');

        // Window artifacts should only be attached to the process, not the task run
        app(WindowProcessService::class)->completeWindowProcess($this->taskProcess, [$artifact]);

        // Mark the window process as complete
        $this->complete();
    }

    /**
     * Run the merge process.
     * Merges all window results into final groups.
     */
    protected function runMergeProcess(): void
    {
        // Use MergeProcessService to handle the merge
        $result = app(MergeProcessService::class)->runMergeProcess($this->taskRun, $this->taskProcess, $this->getMergeConfig());

        // Store metadata in task process
        if (!empty($result['metadata'])) {
            $this->taskProcess->meta = array_merge($this->taskProcess->meta ?? [], $result['metadata']);
            $this->taskProcess->save();
        }

        // Complete with output artifacts
        $this->complete($result['artifacts']);
    }

    /**
     * Create window processes for comparing adjacent files.
     * Called from the initial process run.
     */
    protected function createWindowProcesses(): void
    {
        // Use WindowProcessService to create window processes
        app(WindowProcessService::class)->createWindowProcesses(
            $this->taskRun,
            $this->getComparisonWindowSize(),
            $this->getComparisonWindowOverlap()
        );

        // Dispatch the window processes
        TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);
    }

    /**
     * Prepare the task run.
     * The standard TaskRunnerService::prepareTaskProcesses() creates a Default Task process automatically.
     */
    #[\Override]
    public function prepareRun(): void
    {
        parent::prepareRun();
        static::logDebug('File organization task run prepared');
    }

    /**
     * Called after all parallel processes have completed.
     * Creates a merge process to combine all window results, or resolution processes as needed.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        $orchestrator = app(ResolutionOrchestrator::class);

        // First, try to create merge process if windows are done
        $mergeCreated = $orchestrator->createMergeProcessIfReady($this->taskRun);

        // Then create resolution processes if merge has identified issues
        if (!$mergeCreated) {
            $orchestrator->createResolutionProcesses($this->taskRun);
        }
    }

    /**
     * Override setupAgentThread to use simplified artifact filtering.
     * Only sends page_number from metadata, along with the file itself.
     */
    public function setupAgentThread($artifacts = [], $contextArtifacts = []): AgentThread
    {
        $this->activity("Setting up agent thread for: {$this->taskRun->taskDefinition->agent->name}", 5);

        // Use AgentThreadService for comparison window threads
        $agentThread = app(AgentThreadService::class)->setupComparisonWindowThread(
            $this->taskRun->taskDefinition,
            $this->taskRun,
            collect($artifacts)
        );

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        return $agentThread;
    }

    /**
     * Run low confidence resolution process.
     * Reviews files with uncertain grouping assignments and updates existing merged artifacts.
     */
    protected function runLowConfidenceResolution(): void
    {
        static::logDebug('Starting low confidence resolution');

        $lowConfidenceFiles = $this->taskProcess->meta['low_confidence_files'] ?? [];

        if (empty($lowConfidenceFiles)) {
            static::logDebug('No low-confidence files to resolve');
            $this->complete();

            return;
        }

        static::logDebug('Resolving ' . count($lowConfidenceFiles) . ' low-confidence files');

        // Setup agent thread with uncertain files and context
        $uncertainFileIds   = array_column($lowConfidenceFiles, 'file_id');
        $uncertainArtifacts = $this->taskProcess->inputArtifacts()
            ->whereIn('artifacts.id', $uncertainFileIds)
            ->get();

        $agentThread = app(AgentThreadService::class)->setupLowConfidenceResolutionThread(
            $this->taskRun->taskDefinition,
            $this->taskRun,
            $uncertainArtifacts,
            $lowConfidenceFiles
        );

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from resolution agent thread');
        }

        // Validate resolution has no duplicate pages
        app(ValidationService::class)->validateNoDuplicatePages($artifact->json_content);

        static::logDebug('Resolution completed successfully');

        // Apply resolution decisions to existing merged artifacts
        app(ArtifactResolutionService::class)->applyResolutionToMergedArtifacts($this->taskRun, $artifact->json_content);

        // Delete the temporary resolution artifact - we don't need it as output
        $artifact->delete();

        $this->complete();
    }

    /**
     * Run null group resolution process.
     * These are files where no clear identifier was found, but they're between two different groups.
     * Ask the LLM to decide which adjacent group they should belong to.
     */
    protected function runNullGroupResolution(): void
    {
        static::logDebug('Starting null group resolution');

        $nullGroupFiles = $this->taskProcess->meta['null_groups_needing_llm'] ?? [];

        if (empty($nullGroupFiles)) {
            static::logDebug('No null group files to resolve');
            $this->complete();

            return;
        }

        static::logDebug('Resolving ' . count($nullGroupFiles) . ' null group files');

        // Get null file IDs and page numbers
        $nullFileIds     = array_column($nullGroupFiles, 'file_id');
        $nullPageNumbers = array_column($nullGroupFiles, 'page_number');

        // Gather context pages (2 before and 2 after each null file)
        $contextPageNumbers = app(PageContextService::class)->gatherContextPageNumbers($nullPageNumbers);

        static::logDebug('Including ' . count($contextPageNumbers) . ' context pages for better LLM decision making');

        // Fetch all artifacts (null files + context pages)
        $allPageNumbers = array_unique(array_merge($nullPageNumbers, $contextPageNumbers));
        $allArtifacts   = $this->taskProcess->inputArtifacts()
            ->join('stored_file_storables', function ($join) {
                $join->on('artifacts.id', '=', 'stored_file_storables.storable_id')
                    ->where('stored_file_storables.storable_type', '=', 'App\Models\Task\Artifact');
            })
            ->join('stored_files', 'stored_file_storables.stored_file_id', '=', 'stored_files.id')
            ->whereIn('stored_files.page_number', $allPageNumbers)
            ->select('artifacts.*', 'stored_files.page_number')
            ->orderBy('stored_files.page_number')
            ->get()
            ->unique('id');

        $agentThread = app(AgentThreadService::class)->setupNullGroupResolutionThread(
            $this->taskRun->taskDefinition,
            $this->taskRun,
            $allArtifacts,
            $nullGroupFiles,
            $nullFileIds
        );

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from null group resolution agent thread');
        }

        // Validate resolution has no duplicate pages
        app(ValidationService::class)->validateNoDuplicatePages($artifact->json_content);

        static::logDebug('Null group resolution completed successfully');

        // Apply resolution decisions to existing merged artifacts
        app(ArtifactResolutionService::class)->applyResolutionToMergedArtifacts($this->taskRun, $artifact->json_content);

        // Delete the temporary resolution artifact - we don't need it as output
        $artifact->delete();

        $this->complete();
    }

    /**
     * Run the duplicate group resolution process.
     * Reviews ALL group names for spelling corrections and potential merges.
     */
    protected function runDuplicateGroupResolution(): void
    {
        static::logDebug('Starting group name deduplication');

        $groupsForDeduplication = $this->taskProcess->meta['groups_for_deduplication'] ?? [];

        if (empty($groupsForDeduplication)) {
            static::logDebug('No groups to deduplicate');
            $this->complete();

            return;
        }

        static::logDebug('Deduplicating ' . count($groupsForDeduplication) . ' groups');

        // Setup agent thread for group deduplication
        $agentThread = app(AgentThreadService::class)->setupDuplicateGroupResolutionThread(
            $this->taskRun->taskDefinition,
            $this->taskRun,
            $groupsForDeduplication
        );

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from group deduplication agent thread');
        }

        static::logDebug('Group deduplication completed successfully');

        // Apply resolution decisions to merge duplicate groups
        app(MergeProcessService::class)->applyDuplicateGroupResolution($this->taskRun, $artifact->json_content);

        // Delete the temporary resolution artifact - we don't need it as output
        $artifact->delete();

        $this->complete();
    }

    /**
     * Get the comparison window size from configuration.
     */
    protected function getComparisonWindowSize(): int
    {
        return $this->config('comparison_window_size', self::DEFAULT_COMPARISON_WINDOW_SIZE);
    }

    /**
     * Get the comparison window overlap from configuration.
     */
    protected function getComparisonWindowOverlap(): int
    {
        return $this->config('comparison_window_overlap', self::DEFAULT_COMPARISON_WINDOW_OVERLAP);
    }

    /**
     * Get the group confidence threshold from configuration.
     */
    protected function getGroupConfidenceThreshold(): int
    {
        return $this->config('group_confidence_threshold', self::DEFAULT_GROUP_CONFIDENCE_THRESHOLD);
    }

    /**
     * Get the adjacency boundary threshold from configuration.
     */
    protected function getAdjacencyBoundaryThreshold(): int
    {
        return $this->config('adjacency_boundary_threshold', self::DEFAULT_ADJACENCY_BOUNDARY_THRESHOLD);
    }

    /**
     * Get the blank page handling strategy from configuration.
     */
    protected function getBlankPageHandling(): string
    {
        return $this->config('blank_page_handling', self::DEFAULT_BLANK_PAGE_HANDLING);
    }

    /**
     * Get the name similarity threshold from configuration.
     */
    protected function getNameSimilarityThreshold(): float
    {
        return (float) $this->config('name_similarity_threshold', self::DEFAULT_NAME_SIMILARITY_THRESHOLD);
    }

    /**
     * Get the max sliding iterations from configuration.
     */
    protected function getMaxSlidingIterations(): int
    {
        return $this->config('max_sliding_iterations', self::DEFAULT_MAX_SLIDING_ITERATIONS);
    }

    /**
     * Get the merge configuration array to pass to MergeProcessService.
     */
    protected function getMergeConfig(): array
    {
        return [
            'group_confidence_threshold'  => $this->getGroupConfidenceThreshold(),
            'adjacency_boundary_threshold' => $this->getAdjacencyBoundaryThreshold(),
            'blank_page_handling'          => $this->getBlankPageHandling(),
            'name_similarity_threshold'    => $this->getNameSimilarityThreshold(),
            'max_sliding_iterations'       => $this->getMaxSlidingIterations(),
        ];
    }
}
