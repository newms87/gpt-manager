<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\Artifact;
use App\Services\Task\TaskRunnerService;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;

class TaskRunnerBase implements TaskRunnerContract
{
    use HasDebugLogging;

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
        return app()->makeWith(static::class, ['taskRun' => $taskRun, 'taskProcess' => $taskProcess]);
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
        $this->complete($this->taskProcess->inputArtifacts);
    }

    public function complete(array|Collection $artifacts = []): void
    {
        static::log("Task process completed: $this->taskProcess");

        if ($artifacts) {
            foreach($artifacts as $artifact) {
                if (!($artifact instanceof Artifact)) {
                    throw new ValidationError("Invalid artifact provided: artifacts should be instance of Artifact, instead received: " . (is_object($artifact) ? get_class($artifact) : json_encode($artifact)));
                }
                static::log("Attaching $artifact");
                $this->taskProcess->outputArtifacts()->attach($artifact);
            }
            $this->taskProcess->updateRelationCounter('outputArtifacts');
        }

        // Finished running the process
        TaskRunnerService::processCompleted($this->taskProcess);
    }
}
