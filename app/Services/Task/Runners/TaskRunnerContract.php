<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcessListener;

interface TaskRunnerContract
{
    /**
     * Run the task process and call the TaskRunnerService::processCompleted method when done
     */
    public function run(): void;

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
