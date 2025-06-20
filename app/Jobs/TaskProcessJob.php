<?php

namespace App\Jobs;

use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\TaskProcessExecutorService;
use Exception;
use Newms87\Danx\Jobs\Job;

class TaskProcessJob extends Job
{
    public int $timeout = 610;

    public function __construct(private ?TaskRun $taskRun = null, private ?WorkflowRun $workflowRun = null)
    {
        if (!$this->taskRun && !$this->workflowRun) {
            throw new Exception('Task process job needs workflow run or task run');
        }
        parent::__construct();
    }

    public function ref(): string
    {
        // Generate a unique ref for each job instance to allow multiple workers
        // Use microtime to ensure uniqueness across concurrent dispatches
        $uniqueId = uniqid('', true);

        if ($this->workflowRun) {
            return 'task-process:workflow-' . $this->workflowRun->id . ':' . $uniqueId;
        }

        return 'task-process:task-run-' . $this->taskRun->id . ':' . $uniqueId;
    }

    public function run(): void
    {
        $executor = app(TaskProcessExecutorService::class);

        if ($this->workflowRun) {
            $executor->runNextTaskProcessForWorkflowRun($this->workflowRun);
        } elseif ($this->taskRun) {
            $executor->runNextTaskProcessForTaskRun($this->taskRun);
        }
    }
}
