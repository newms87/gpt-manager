<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as EloquentCollection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;

class BaseTaskRunner implements TaskRunnerContract
{
    use HasDebugLogging;

    const string RUNNER_NAME = 'Base';

    // Indicates if the class is a workflow trigger
    const bool   IS_TRIGGER = false;

    protected ?TaskDefinition $taskDefinition;
    protected ?TaskRun        $taskRun;
    protected ?TaskProcess    $taskProcess = null;

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
        $this->taskRun        = $taskRun;
        $this->taskDefinition = $taskRun->taskDefinition;

        return $this;
    }

    public function setTaskProcess(TaskProcess $taskProcess): static
    {
        $this->taskProcess = $taskProcess;

        return $this;
    }

    public function config($key = null, $default = null): mixed
    {
        if ($key) {
            return $this->taskDefinition->task_runner_config[$key] ?? $default;
        }

        return $this->taskDefinition->task_runner_config ?? [];
    }

    public function prepareRun(): void
    {
        $this->taskRun->name = static::RUNNER_NAME . ': ' . $this->taskDefinition->name;
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

        if (!$this->taskProcess) {
            throw new ValidationError("TaskProcess is not set in this context");
        }

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

    /**
     * Complete the task process and attach any artifacts to the task run and process
     *
     * @param Artifact[]|Collection $artifacts
     */
    public function complete(array|Collection|EloquentCollection $artifacts = []): void
    {
        static::log("Task process completed: $this->taskProcess");

        if ($artifacts) {
            TaskRunnerService::validateArtifacts($artifacts);

            foreach($artifacts as $artifact) {
                static::log("Attaching $artifact");
                $artifact->task_definition_id = $this->taskProcess->taskRun->task_definition_id;
                if (!$artifact->position) {
                    $artifact->position = $this->resolveArtifactPosition($artifact, $artifacts);
                }
                $artifact->save();
            }

            $this->attachArtifactsToTaskAndProcess($artifacts);
        }

        if ($this->taskProcess->percent_complete < 100) {
            $this->activity("Task completed successfully", 100);
        }

        // Finished running the process
        TaskProcessRunnerService::complete($this->taskProcess);
    }

    public function attachArtifactsToTaskAndProcess($artifacts): void
    {
        $outputMode  = $this->taskDefinition->output_artifact_mode;
        $artifactIds = collect($artifacts)->pluck('id')->toArray();

        switch($outputMode) {
            case TaskDefinition::OUTPUT_ARTIFACT_MODE_GROUP_ALL:
                static::log("Grouping all artifacts into a single artifact");

                $topLevelArtifact = $this->resolveSingletonTaskRunArtifact();
                $topLevelArtifact->children()->saveMany($artifacts);

                // The processes will still output all the artifacts it produces, the roll up happens at the task level
                // so keep the task run artifact IDs set to null
                $taskRunArtifactIds = null;
                $processArtifactIds = $artifactIds;
                break;

            case TaskDefinition::OUTPUT_ARTIFACT_MODE_PER_PROCESS:
                static::log("Grouping all artifacts into a single artifact per process");

                $processArtifact = Artifact::create([
                    'name'               => $this->taskProcess->name,
                    'task_definition_id' => $this->taskDefinition->id,
                ]);
                $processArtifact->children()->saveMany($artifacts);

                // The process will have a single artifact containing a group of all the produced artifacts
                $processArtifactIds = [$processArtifact->id];

                // The task will have the process artifact group appended
                $taskRunArtifactIds = $processArtifactIds;
                break;

            default:
                static::log("Attaching all artifacts to process and task run");
                
                // By default, all artifacts go to the process and the task
                $taskRunArtifactIds = $artifactIds;
                $processArtifactIds = $artifactIds;
                break;

        }

        // Add the artifact to the list of output artifacts for this process
        $this->taskProcess->outputArtifacts()->sync($processArtifactIds);
        $this->taskProcess->updateRelationCounter('outputArtifacts');

        if ($taskRunArtifactIds) {
            // Also add to the list of output artifacts for this task run
            $this->taskRun->outputArtifacts()->syncWithoutDetaching($taskRunArtifactIds);
            $this->taskRun->updateRelationCounter('outputArtifacts');
        }
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

    /**
     * For group all mode we need to make sure we have 1 single artifact across all processes attached to the task run
     * So lock the task run and resolve the top level artifact
     */
    public function resolveSingletonTaskRunArtifact(): Artifact
    {
        LockHelper::acquire($this->taskRun);

        try {
            $taskRunArtifact = $this->taskRun->outputArtifacts()->first();

            if (!$taskRunArtifact) {
                $taskRunArtifact = Artifact::create([
                    'name'               => $this->taskRun->name,
                    'task_definition_id' => $this->taskDefinition->id,
                ]);
                $this->taskRun->outputArtifacts()->sync($taskRunArtifact);
                $this->taskRun->updateRelationCounter('outputArtifacts');
            }

            return $taskRunArtifact;
        } finally {
            LockHelper::release($this->taskRun);
        }
    }
}
