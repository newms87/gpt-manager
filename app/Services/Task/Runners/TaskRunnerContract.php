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
     * Called when all task processes have completed. This will be called on the same job that ran the last task
     * process.
     */
    public function afterAllProcessesCompleted(): void;

    /**
     * A flag to indicate if this task runner is a workflow triggering task
     */
    public function isTrigger(): bool;

    public function setTaskRun(TaskRun $taskRun): static;

    public function setTaskProcess(TaskProcess $taskProcess): static;

    /** The name of the queue task process jobs should run on */
    public function getQueue(): string;

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
