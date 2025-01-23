<?php

namespace App\Jobs;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Jobs\Job;

class ExecuteTaskProcessJob extends Job
{
    public TaskProcess $taskProcess;

    public int $timeout = 610;

    public function __construct(TaskProcess $taskProcess)
    {
        $this->taskProcess = $taskProcess;
        parent::__construct();
    }

    public function ref(): string
    {
        return 'execute-task-process:' . $this->taskProcess->id;
    }

    public function run()
    {
        TaskRunnerService::runProcess($this->taskProcess);
    }
}
