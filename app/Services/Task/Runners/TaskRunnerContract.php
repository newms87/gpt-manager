<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;

interface TaskRunnerContract
{
    /**
     * Run the task process and call the TaskRunnerService::processCompleted method when done
     */
    public function run(TaskProcess $taskProcess): void;
}
