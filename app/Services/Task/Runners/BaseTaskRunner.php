<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Services\Task\TaskProcessRunnerService;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as EloquentCollection;
use Newms87\Danx\Exceptions\ValidationError;

class BaseTaskRunner implements TaskRunnerContract
{
    use HasDebugLogging;

    const string RUNNER_NAME = 'Base';

    // Indicates if the class is a workflow trigger
    const bool   IS_TRIGGER = false;

    protected ?TaskRun     $taskRun;
    protected ?TaskProcess $taskProcess;

    public static function make(): static
    {
        return app(static::class);
    }

    public function isTrigger(): bool
    {
        return static::IS_TRIGGER;
    }

    public function setTaskRun(TaskRun $taskRun): static
    {
        $this->taskRun = $taskRun;

        return $this;
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
        static::log("Step: $step");

        $this->taskRun->update([
            'step'             => $step,
            'percent_complete' => $percentComplete ?? $this->taskRun->percent_complete,
        ]);
    }

    public function activity(string $activity, float $percentComplete = null): void
    {
        static::log("Activity: $activity");

        $this->taskProcess->update([
            'activity'         => $activity,
            'percent_complete' => $percentComplete ?? $this->taskProcess->percent_complete,
        ]);
    }

    public function run(): void
    {
        $this->complete($this->taskProcess->inputArtifacts);
    }

    public function eventTriggered(TaskProcessListener $taskProcessListener): void
    {
        static::log("Event Triggered $taskProcessListener");
    }

    public function complete(array|Collection|EloquentCollection $artifacts = []): void
    {
        static::log("Task process completed: $this->taskProcess");

        if ($artifacts) {
            $artifactIds = [];
            foreach($artifacts as $artifact) {
                if (!($artifact instanceof Artifact)) {
                    throw new ValidationError("Invalid artifact provided: artifacts should be instance of Artifact, instead received: " . (is_object($artifact) ? get_class($artifact) : json_encode($artifact)));
                }
                static::log("Attaching $artifact");
                $artifactIds[] = $artifact->id;

                $artifact->task_definition_id = $this->taskProcess->taskRun->task_definition_id;
                $artifact->position           = $this->resolveArtifactPosition($artifact, $artifacts);
                $artifact->save();
            }

            // Add the artifact to the list of output artifacts for this process
            $this->taskProcess->outputArtifacts()->syncWithoutDetaching($artifactIds);
            // Also add to the list of output artifacts for this task run
            $this->taskRun->outputArtifacts()->syncWithoutDetaching($artifactIds);

            $this->taskProcess->updateRelationCounter('outputArtifacts');
            $this->taskRun->updateRelationCounter('outputArtifacts');
        }

        if ($this->taskProcess->percent_complete < 100) {
            $this->activity("Task completed successfully", 100);
        }

        // Finished running the process
        TaskProcessRunnerService::complete($this->taskProcess);
    }

    /**
     * Resolve the position of the artifact relative to the list of artifacts
     *
     * @param Artifact[] $artifactList
     */
    public function resolveArtifactPosition(Artifact $targetArtifact, $artifactList): int
    {
        if ($targetArtifact->storedFiles->isNotEmpty()) {
            $minPage = 9999999;
            foreach($targetArtifact->storedFiles as $storedFile) {
                $minPage = min($minPage, $storedFile->page_number ?? 0);
            }

            return $minPage;
        }

        $artifactsBefore = 0;
        foreach($artifactList as $refArtifact) {
            if ($targetArtifact->name > $refArtifact->name || $refArtifact->storedFiles) {
                $artifactsBefore++;
            }
        }

        return $artifactsBefore;
    }

    public function afterAllProcessesCompleted(): void
    {
        static::log("All processes completed.");
    }
}
