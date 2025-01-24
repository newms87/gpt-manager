<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;

abstract class TaskRunnerAbstract implements TaskRunnerContract
{
    protected TaskProcess $taskProcess;

    public function __construct(TaskProcess $taskProcess)
    {
        $this->taskProcess = $taskProcess;
    }

    abstract public function run(): void;
}
