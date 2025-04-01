<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;

interface TaskRunnerContract
{
    /**
     * Run the task process and call the TaskRunnerService::processCompleted method when done
     */
    public function run(): void;

    /**
     * A flag to indicate if this task runner is a workflow triggering task
     */
    public function isTrigger(): bool;

    public function setTaskRun(TaskRun $taskRun): static;

    public function setTaskProcess(TaskProcess $taskProcess): static;

    /**
     * Prepare the task runner for running
     */
    public function prepareRun(): void;

    /**
     * Prepare the task process for processing
     */
    public function prepareProcess(): void;

    /**
     * Fire the event the task process was listening to.
     * If the task process is complete after this call, call the TaskProcessRunnerService::complete method when
     * finished successfully
     */
    public function eventTriggered(TaskProcessListener $taskProcessListener): void;
}
