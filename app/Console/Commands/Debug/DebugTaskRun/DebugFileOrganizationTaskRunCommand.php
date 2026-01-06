<?php

namespace App\Console\Commands\Debug\DebugTaskRun;

use App\Services\Task\Debug\DebugTaskRunService;
use App\Services\Task\Debug\FileOrganizationDebugService;
use Override;

class DebugFileOrganizationTaskRunCommand extends DebugTaskRunCommand
{
    protected $signature = 'debug:file-organization-task-run {task-run : TaskRun ID or TaskProcess ID}
        {--messages : Show agent thread messages}
        {--artifacts : Show artifact JSON content}
        {--raw : Show raw artifact data}
        {--process= : Show detailed info for specific task process ID}
        {--run : Create new task run with same inputs}
        {--rerun : Reset and re-dispatch task run}
        {--dedup : Show full deduplication metadata}
        {--window= : Show detailed info for specific window (e.g., 1-10)}
        {--page= : Show all data about a specific page number across all windows}
        {--group= : Show all pages assigned to a specific group name}
        {--mismatches : Show pages with conflicting group assignments across windows}
        {--rerun-merge : Delete existing merge output and rerun the merge process}
        {--rerun-dedup : Delete existing dedup output and rerun the duplicate group resolution process}
        {--reset-from-windows : Delete merge and all resolution processes, then re-create from windows}';

    protected $description = 'Debug a FileOrganization TaskRun to understand window processes, group assignments, and merge results';

    #[Override]
    public function handle(): int
    {
        $debugService = app(DebugTaskRunService::class);

        if (!$this->resolveTaskRun($debugService)) {
            return 1;
        }

        $fileOrgService = app(FileOrganizationDebugService::class);

        // Handle FileOrganization-specific options
        if ($windowRange = $this->option('window')) {
            return $fileOrgService->showWindowDetail($this->taskRun, $windowRange, $this);
        }

        if ($page = $this->option('page')) {
            return $fileOrgService->showPageAnalysis($this->taskRun, (int)$page, $this);
        }

        if ($group = $this->option('group')) {
            return $fileOrgService->showGroupPages($this->taskRun, $group, $this);
        }

        if ($this->option('mismatches')) {
            return $fileOrgService->showMismatches($this->taskRun, $this);
        }

        if ($this->option('rerun-merge')) {
            return $fileOrgService->rerunMerge($this->taskRun, $this);
        }

        if ($this->option('rerun-dedup')) {
            return $fileOrgService->rerunDedup($this->taskRun, $this);
        }

        if ($this->option('reset-from-windows')) {
            return $fileOrgService->resetFromWindows($this->taskRun, $this);
        }

        // Fall back to parent handle for base options (--run, --rerun, --process)
        return parent::handle();
    }

    #[Override]
    protected function showOverview(DebugTaskRunService $debugService): int
    {
        $showRaw       = $this->option('raw');
        $showArtifacts = $this->option('artifacts');
        $showMessages  = $this->option('messages');
        $showDedup     = $this->option('dedup');
        $verbose       = $this->option('verbose');

        $fileOrgService = app(FileOrganizationDebugService::class);
        $fileOrgService->showOverview(
            $this->taskRun,
            $this,
            $showRaw,
            $showArtifacts,
            $showMessages,
            $showDedup,
            $verbose
        );

        return 0;
    }
}
