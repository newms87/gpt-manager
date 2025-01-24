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

    public static function make(TaskProcess $taskProcess): TaskRunnerContract
    {
        return new static($taskProcess);
    }

    abstract public function run(): void;
}
