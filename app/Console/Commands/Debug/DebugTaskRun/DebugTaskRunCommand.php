<?php

namespace App\Console\Commands\Debug\DebugTaskRun;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Debug\DebugTaskRunService;
use Illuminate\Console\Command;

class DebugTaskRunCommand extends Command
{
    protected $signature = 'debug:task-run {task-run : TaskRun ID or TaskProcess ID}
        {--messages : Show agent thread messages}
        {--artifacts : Show artifact JSON content}
        {--raw : Show raw artifact data}
        {--process= : Show detailed info for specific task process ID}
        {--status= : Filter processes by status (Pending, Running, Completed, Failed)}
        {--timing : Show process timing information}
        {--run : Create new task run with same inputs}
        {--rerun : Reset and re-dispatch task run}
        {--api-logs : Show API logs for the task process (uses most recent job dispatch)}
        {--job-dispatches : List all job dispatches for the task process}
        {--job-dispatch= : Specify which job dispatch ID to show API logs for (use with --api-logs)}';

    protected $description = 'Debug a TaskRun to understand agent communication and results';

    protected ?TaskRun $taskRun = null;

    protected ?TaskProcess $taskProcess = null;

    public function handle(): int
    {
        $debugService = app(DebugTaskRunService::class);

        if (!$this->resolveTaskRun($debugService)) {
            return 1;
        }

        if ($this->option('run')) {
            return $debugService->createNewTaskRun($this->taskRun, $this);
        }

        if ($this->option('rerun')) {
            return $debugService->resetAndRerunTaskRun($this->taskRun, $this);
        }

        if ($this->option('job-dispatches')) {
            return $this->handleJobDispatches($debugService);
        }

        if ($this->option('api-logs')) {
            return $this->handleApiLogs($debugService);
        }

        if ($this->option('process')) {
            return $debugService->showProcessDetail($this->taskRun, (int)$this->option('process'), $this);
        }

        if ($statusFilter = $this->option('status')) {
            $debugService->showProcessesByStatus($this->taskRun, $statusFilter, $this);

            return 0;
        }

        if ($this->option('timing')) {
            $debugService->showTiming($this->taskRun, $this);

            return 0;
        }

        return $this->showOverview($debugService);
    }

    /**
     * Resolve TaskRun from argument (can be TaskRun ID or TaskProcess ID)
     */
    protected function resolveTaskRun(DebugTaskRunService $debugService): bool
    {
        // Skip if already resolved (prevents duplicate resolution when subclass calls parent::handle())
        if ($this->taskRun) {
            return true;
        }

        $result = $debugService->resolveTaskRun($this->argument('task-run'));

        if (!$result) {
            $this->error("No TaskRun or TaskProcess found with ID: {$this->argument('task-run')}");

            return false;
        }

        $this->taskRun     = $result['taskRun'];
        $this->taskProcess = $result['taskProcess'];

        if ($this->taskProcess) {
            $this->info("Resolved TaskProcess {$this->taskProcess->id} -> TaskRun {$this->taskRun->id}");

            // Auto-set --process option if not already set
            if (!$this->option('process')) {
                $this->input->setOption('process', $this->taskProcess->id);
            }
        }

        return true;
    }

    /**
     * Show generic task run overview
     */
    protected function showOverview(DebugTaskRunService $debugService): int
    {
        $debugService->showOverview($this->taskRun, $this);

        if ($this->option('messages')) {
            $debugService->showAgentThreadMessages($this->taskRun, $this);
        }

        $inputArtifacts  = $this->taskRun->inputArtifacts()->orderBy('position')->get();
        $outputArtifacts = $this->taskRun->outputArtifacts()->get();

        if ($this->option('artifacts')) {
            $debugService->showArtifactContent($inputArtifacts, $outputArtifacts, $this);
        }

        if ($this->option('raw')) {
            $debugService->showRawArtifactData($inputArtifacts, $outputArtifacts, $this);
        }

        return 0;
    }

    /**
     * Handle --job-dispatches option to list all job dispatches for a task process.
     */
    protected function handleJobDispatches(DebugTaskRunService $debugService): int
    {
        if (!$this->taskProcess) {
            $this->error('The --job-dispatches option requires a TaskProcess ID.');
            $this->line('Please specify a TaskProcess ID directly, or use --process= to specify which process to view.');

            return 1;
        }

        $debugService->showJobDispatches($this->taskProcess, $this);

        return 0;
    }

    /**
     * Handle --api-logs option to show API logs for a task process.
     */
    protected function handleApiLogs(DebugTaskRunService $debugService): int
    {
        if (!$this->taskProcess) {
            $this->error('The --api-logs option requires a TaskProcess ID.');
            $this->line('Please specify a TaskProcess ID directly, or use --process= to specify which process to view.');

            return 1;
        }

        $jobDispatchId = $this->option('job-dispatch') ? (int)$this->option('job-dispatch') : null;
        $debugService->showApiLogs($this->taskProcess, $jobDispatchId, $this);

        return 0;
    }
}
