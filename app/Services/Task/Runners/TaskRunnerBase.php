<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\Artifact;
use App\Services\Task\TaskRunnerService;
use Illuminate\Support\Facades\Log;

class TaskRunnerBase implements TaskRunnerContract
{
    const string RUNNER_NAME = 'Base Task Runner';

    protected TaskRun      $taskRun;
    protected ?TaskProcess $taskProcess;

    public function __construct(TaskRun $taskRun, TaskProcess $taskProcess = null)
    {
        $this->taskRun     = $taskRun;
        $this->taskProcess = $taskProcess;
    }

    public static function make(TaskRun $taskRun, TaskProcess $taskProcess = null): TaskRunnerContract
    {
        return new static($taskRun, $taskProcess);
    }

    public function setTaskProcess(TaskProcess $taskProcess): static
    {
        $this->taskProcess = $taskProcess;

        return $this;
    }

    public function prepareRun(): void
    {
        $this->taskRun->name = static::RUNNER_NAME . ': ' . $this->taskRun->taskDefinition->name;
        $this->step('Prepare', 1);
    }

    public function prepareProcess(): void
    {
        $this->activity('Configuring process for running the base task', 1);
    }

    public function step(string $step, float $percentComplete = null): void
    {
        $this->taskRun->update([
            'step'             => $step,
            'percent_complete' => $percentComplete ?? $this->taskRun->percent_complete,
        ]);
    }

    public function activity(string $activity, float $percentComplete = null): void
    {
        $this->taskProcess->update([
            'activity'         => $activity,
            'percent_complete' => $percentComplete ?? $this->taskProcess->percent_complete,
        ]);
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
            $this->taskProcess->updateRelationCounter('outputArtifacts');
        }

        // Finished running the process
        TaskRunnerService::processCompleted($this->taskProcess);
    }
}
