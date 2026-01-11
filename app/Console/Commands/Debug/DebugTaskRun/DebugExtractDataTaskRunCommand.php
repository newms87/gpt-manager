<?php

namespace App\Console\Commands\Debug\DebugTaskRun;

use App\Services\Task\Debug\DebugTaskRunService;
use App\Services\Task\Debug\ExtractDataDebugService;
use Override;

class DebugExtractDataTaskRunCommand extends DebugTaskRunCommand
{
    protected $signature = 'debug:extract-data-task-run {task-run? : TaskRun ID, TaskProcess ID, or JobDispatch ID}
        {--messages : Show agent thread messages}
        {--artifacts : Show artifact JSON content}
        {--raw : Show raw artifact data}
        {--process= : Show detailed info for specific task process ID}
        {--run : Create new task run with same inputs}
        {--rerun : Reset and re-dispatch task run}
        {--status= : Filter processes by status (Pending, Running, Completed, Failed)}
        {--timing : Show process timing information}
        {--api-logs : Show API logs for the task process (uses most recent job dispatch)}
        {--job-dispatches : List all job dispatches for the task process}
        {--job-dispatch= : Specify which job dispatch ID to show API logs for (use with --api-logs)}
        {--list-dispatches : List recent job dispatches to find IDs}
        {--run-process= : Run a specific task process ID synchronously to debug exceptions}
        {--classify-status : Show status of all classify processes}
        {--artifact-tree : Show parent/child artifact hierarchy}
        {--resolved-objects : Show all TeamObjects created during extraction}
        {--taskrun-meta : Show the full TaskRun meta data}
        {--level-progress : Show extraction level progress for each level}
        {--cached-plan : Show cached extraction plan fragment selectors}
        {--clear-cached-plan : Clear cached extraction plan}
        {--show-schema= : Show the extraction response schema for a specific task process ID}';

    protected $description = 'Debug an ExtractData TaskRun with specialized extraction debugging options';

    #[Override]
    public function handle(): int
    {
        $debugService = app(DebugTaskRunService::class);

        // Handle --list-dispatches before requiring task-run argument
        if ($this->option('list-dispatches')) {
            return $this->listRecentJobDispatches($debugService);
        }

        if (!$this->resolveTaskRun($debugService)) {
            return 1;
        }

        $extractDataService = app(ExtractDataDebugService::class);

        // Handle ExtractData-specific options first
        if ($this->option('run-process')) {
            return $extractDataService->runProcess($this->taskRun, (int)$this->option('run-process'), $this);
        }

        if ($this->option('classify-status')) {
            $extractDataService->showClassifyProcesses($this->taskRun, $this);

            return 0;
        }

        if ($this->option('artifact-tree')) {
            $extractDataService->showArtifactStructure($this->taskRun, $this);

            return 0;
        }

        if ($this->option('resolved-objects')) {
            $extractDataService->showResolvedObjects($this->taskRun, $this);

            return 0;
        }

        if ($this->option('taskrun-meta')) {
            $extractDataService->showTaskRunMeta($this->taskRun, $this);

            return 0;
        }

        if ($this->option('level-progress')) {
            $extractDataService->showLevelProgress($this->taskRun, $this);

            return 0;
        }

        if ($this->option('cached-plan')) {
            $extractDataService->showCachedPlan($this->taskRun, $this);

            return 0;
        }

        if ($this->option('clear-cached-plan')) {
            $extractDataService->clearCachedPlan($this->taskRun, $this);

            return 0;
        }

        if ($this->option('show-schema')) {
            $extractDataService->showExtractionSchema($this->taskRun, (int)$this->option('show-schema'), $this);

            return 0;
        }

        // Fall back to parent::handle() for base functionality
        return parent::handle();
    }

    #[Override]
    protected function showOverview(DebugTaskRunService $debugService): int
    {
        app(ExtractDataDebugService::class)->showOverview($this->taskRun, $this);

        return 0;
    }
}
