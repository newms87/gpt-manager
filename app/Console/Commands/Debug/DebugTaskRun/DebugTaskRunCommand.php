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
        {--run : Create new task run with same inputs}
        {--rerun : Reset and re-dispatch task run}';

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

        if ($this->option('process')) {
            return $debugService->showProcessDetail($this->taskRun, (int)$this->option('process'), $this);
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
}
