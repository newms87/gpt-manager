<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\Artifact;
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
        $this->complete();
    }

    public function complete(Artifact $artifact = null): void
    {
        Log::debug("TaskRunnerBase: task process completed: $this->taskProcess");
        
        if ($artifact) {
            $this->taskProcess->outputArtifacts()->attach($artifact);
        }

        // Finished running the process
        TaskRunnerService::processCompleted($this->taskProcess);
    }
}
