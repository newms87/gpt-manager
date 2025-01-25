<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskRunnerService;
use Illuminate\Support\Facades\Log;

class TaskRunnerBase implements TaskRunnerContract
{
    protected TaskProcess $taskProcess;

    public function __construct(TaskProcess $taskProcess)
    {
        $this->taskProcess = $taskProcess;
    }

    public static function make(TaskProcess $taskProcess): TaskRunnerContract
    {
        return new static($taskProcess);
    }

    public function run(): void
    {
        Log::debug("TaskRunnerBase: task process completed: $this->taskProcess");

        // Finished running the process
        TaskRunnerService::processCompleted($this->taskProcess);
    }
}
