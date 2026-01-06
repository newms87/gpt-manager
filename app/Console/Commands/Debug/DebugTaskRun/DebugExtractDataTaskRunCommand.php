<?php

namespace App\Console\Commands\Debug\DebugTaskRun;

use App\Services\Task\Debug\DebugTaskRunService;
use App\Services\Task\Debug\ExtractDataDebugService;
use Override;

class DebugExtractDataTaskRunCommand extends DebugTaskRunCommand
{
    protected $signature = 'debug:extract-data-task-run {task-run : TaskRun ID or TaskProcess ID}
        {--messages : Show agent thread messages}
        {--artifacts : Show artifact JSON content}
        {--raw : Show raw artifact data}
        {--process= : Show detailed info for specific task process ID}
        {--run : Create new task run with same inputs}
        {--rerun : Reset and re-dispatch task run}
        {--run-process= : Run a specific task process ID synchronously to debug exceptions}
        {--classify-status : Show status of all classify processes}
        {--artifact-tree : Show parent/child artifact hierarchy}
        {--resolved-objects : Show all TeamObjects created during extraction}
        {--taskrun-meta : Show the full TaskRun meta data}
        {--level-progress : Show extraction level progress for each level}
        {--cached-plan : Show cached extraction plan fragment selectors}
        {--clear-cached-plan : Clear cached extraction plan}';

    protected $description = 'Debug an ExtractData TaskRun with specialized extraction debugging options';

    #[Override]
    public function handle(): int
    {
        $debugService = app(DebugTaskRunService::class);

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
