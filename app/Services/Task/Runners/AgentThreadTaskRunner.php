<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskRunnerService;

class AgentThreadTaskRunner implements TaskRunnerContract
{
    public function run(TaskProcess $taskProcess): void
    {
        // Finished running the process
        TaskRunnerService::processCompleted($taskProcess);
    }
}
