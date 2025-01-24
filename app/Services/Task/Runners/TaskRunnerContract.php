<?php

namespace App\Services\Task\Runners;

interface TaskRunnerContract
{
    /**
     * Run the task process and call the TaskRunnerService::processCompleted method when done
     */
    public function run(): void;
}
