<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Services\Task\FileOrganization\AgentThreadService;
use App\Services\Task\FileOrganization\FileOrganizationStateOrchestrator;
use App\Services\Task\FileOrganization\MergeProcessService;
use App\Services\Task\FileOrganization\ValidationService;
use App\Services\Task\FileOrganization\WindowProcessService;
use App\Services\Task\Traits\HasTranscodePrerequisite;
use App\Services\Task\TranscodePrerequisiteService;
use Newms87\Danx\Exceptions\ValidationError;

class FileOrganizationTaskRunner extends AgentThreadTaskRunner
{
    use HasTranscodePrerequisite;

    public const string RUNNER_NAME = 'File Organization';

    public const string OPERATION_COMPARISON_WINDOW = 'Comparison Window',
        OPERATION_MERGE                             = 'Merge',
        OPERATION_DUPLICATE_GROUP_RESOLUTION        = 'Duplicate Group Resolution';

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
            BaseTaskRunner::OPERATION_DEFAULT                 => $this->runInitializeOperation(),
            TranscodePrerequisiteService::OPERATION_TRANSCODE => $this->runTranscodeOperation(),
            self::OPERATION_COMPARISON_WINDOW                 => $this->runComparisonWindow(),
            self::OPERATION_MERGE                             => $this->runMergeProcess(),
            self::OPERATION_DUPLICATE_GROUP_RESOLUTION        => $this->runDuplicateGroupResolution(),
        };
    }

    /**
     * Run the initialization operation.
     * Advances to the next phase via the state orchestrator.
     */
    protected function runInitializeOperation(): void
    {
        static::logDebug('Running initialization - advancing to next phase');
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($this->taskRun);
        $this->complete();
    }

    /**
     * Run a comparison window process.
     * Compares adjacent files in the window and groups them.
     */
    protected function runComparisonWindow(): void
    {
        $inputArtifact = $this->taskProcess->inputArtifacts->first();

        if (!$inputArtifact) {
            throw new ValidationError('No input artifact found for Comparison Window process.');
        }

        static::logDebug('Window artifact: ' . $inputArtifact->name);

        // Setup agent thread with the window pages artifact
        $agentThread = $this->setupAgentThread(collect([$inputArtifact]));

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from agent thread');
        }

        // Validate that no page appears in multiple groups
        app(ValidationService::class)->validateNoDuplicatePages($artifact->json_content);

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
     * Advances to the next phase via the state orchestrator.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($this->taskRun);
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
