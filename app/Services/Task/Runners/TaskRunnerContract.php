<?php

namespace App\Services\Task\Runners;

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
}
