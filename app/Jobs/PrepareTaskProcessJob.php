<?php

namespace App\Jobs;

use App\Models\Task\TaskRun;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Jobs\Job;

class PrepareTaskProcessJob extends Job
{
    public TaskRun $taskRun;

    public int $timeout = 610;

    public function __construct(TaskRun $taskRun)
    {
        $this->taskRun = $taskRun;
        parent::__construct();
    }

    public function ref(): string
    {
        return 'prepare-task-processes:' . $this->taskRun->id;
    }

    public function run(): void
    {
        LockHelper::acquire($this->taskRun);

        try {
            TaskRunnerService::prepareTaskProcesses($this->taskRun);
        } finally {
            LockHelper::release($this->taskRun);
        }

        TaskRunnerService::continue($this->taskRun);
    }
}
