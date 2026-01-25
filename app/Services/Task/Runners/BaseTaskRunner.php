<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Services\Task\ArtifactsMergeService;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as EloquentCollection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Models\Utilities\StoredFile;

class BaseTaskRunner implements TaskRunnerContract
{
    use HasDebugLogging;

    const string RUNNER_NAME = 'Base';

    // Indicates if the class is a workflow trigger
    const bool   IS_TRIGGER = false;

    // Default operation type for task processes
    const string OPERATION_DEFAULT = 'Default Task';

    protected ?TaskDefinition $taskDefinition;

    protected ?TaskRun $taskRun;

    protected ?TaskProcess $taskProcess = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function isTrigger(): bool
    {
        return static::IS_TRIGGER;
    }

    /**
     * Get all files from the input artifacts of the task process
     *
     * @return StoredFile[]
     */
    public function getAllFiles($allowedExts = []): array
    {
        $files = [];

        foreach ($this->taskProcess->inputArtifacts as $artifact) {
            foreach ($artifact->storedFiles as $storedFile) {
                if ($allowedExts && !in_array(strtolower(pathinfo($storedFile->filename, PATHINFO_EXTENSION)), $allowedExts)) {
                    continue;
                }

                $files[] = $storedFile;
            }
        }

        return $files;
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

    public function step(string $step, ?float $percentComplete = null): void
    {
        static::logDebug("Step: $step");

        $this->taskRun->update([
            'step'             => $step,
            'percent_complete' => $percentComplete ?? $this->taskRun->percent_complete,
        ]);
    }

    public function activity(string $activity, ?float $percentComplete = null): void
    {
        static::logDebug("Activity: $activity");

        if (!$this->taskProcess) {
            throw new ValidationError('TaskProcess is not set in this context');
        }

        $this->taskProcess->update([
            'activity'         => substr($activity, 0, 1000),
            'percent_complete' => $percentComplete ?? $this->taskProcess->percent_complete,
        ]);
    }

    public function run(): void
    {
        $this->complete($this->taskProcess->inputArtifacts);
    }

    public function eventTriggered(TaskProcessListener $taskProcessListener): void
    {
        static::logDebug("Event Triggered $taskProcessListener");
    }

    /**
     * Complete the task process and attach any artifacts to the task run and process
     *
     * @param  Artifact[]|Collection  $artifacts
     */
    public function complete(array|Collection|EloquentCollection $artifacts = []): void
    {
        static::logDebug("Task process completed: $this->taskProcess");

        if ($artifacts) {
            TaskRunnerService::validateArtifacts($artifacts);
            static::prepareArtifactsForOutput($artifacts);

            static::logDebug('Attaching artifacts to task run and process: ' . collect($artifacts)->pluck('id')->toJson());

            $this->attachArtifactsToTaskAndProcess($artifacts);
        }

        if ($this->taskProcess->percent_complete < 100) {
            $this->activity('Task completed successfully', 100);
        }

        // Finished running the process
        TaskProcessRunnerService::complete($this->taskProcess);
    }

    /**
     * Attach the artifacts to the task run and process considering the output artifact mode defined on the task
     * definition
     */
    public function attachArtifactsToTaskAndProcess($artifacts): void
    {
        $outputMode  = $this->taskDefinition->output_artifact_mode;
        $artifactIds = collect($artifacts)->pluck('id')->toArray();

        switch ($outputMode) {
            case TaskDefinition::OUTPUT_ARTIFACT_MODE_GROUP_ALL:
                static::logDebug('Grouping all artifacts into a single artifact');

                $topLevelArtifact = $this->resolveSingletonTaskRunArtifact($artifacts);
                $topLevelArtifact->assignChildren($artifacts);

                // The processes will still output all the artifacts it produces, the roll up happens at the task level
                // so keep the task run artifact IDs set to null
                $taskRunArtifactIds = null;
                $processArtifactIds = $artifactIds;
                break;

            case TaskDefinition::OUTPUT_ARTIFACT_MODE_PER_PROCESS:
                static::logDebug('Grouping all artifacts into a single artifact per process');

                $processArtifact = $this->createMergedArtifactFromTopLevel($artifacts);

                $processArtifact->assignChildren($artifacts);

                // The process will have a single artifact containing a group of all the produced artifacts
                $processArtifactIds = [$processArtifact->id];

                // The task will have the process artifact group appended
                $taskRunArtifactIds = $processArtifactIds;
                break;

            default:
                static::logDebug('Attaching all artifacts to process and task run');

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
     * Prepare the artifacts for output by associating them to the task definition of this task run
     * and setting their position in the list
     *
     * @param  Artifact[]|Collection  $artifacts
     */
    public function prepareArtifactsForOutput(array|Collection|EloquentCollection $artifacts): void
    {
        $maxLevel = $this->taskDefinition->output_artifact_levels[0] ?? 0;

        foreach ($artifacts as $artifact) {
            // Always make sure the artifact is for this task definition
            $artifact->task_process_id    = $this->taskProcess->id;
            $artifact->task_definition_id = $this->taskDefinition->id;
            if (!$artifact->position) {
                $artifact->position = $this->resolveArtifactPosition($artifact, $artifacts);
            }
            $artifact->save();

            static::logDebug("Trimming artifact hierarchy for $artifact->id, max level: $maxLevel");
            $this->trimArtifactHierarchy($artifact, $maxLevel);
        }
    }

    /**
     * Recursively trim the artifact hierarchy for any children beyond the max level
     */
    public function trimArtifactHierarchy(Artifact $artifact, int $maxLevel, int $currentLevel = 0): void
    {
        // Keep moving down the hierarchy until we've reached the max level
        if ($currentLevel < $maxLevel) {
            foreach ($artifact->children as $child) {
                $this->trimArtifactHierarchy($child, $maxLevel, $currentLevel + 1);
            }

            return;
        }

        $artifact->clearChildren();
    }

    /**
     * Resolve the position of the artifact relative to the list of artifacts
     *
     * @param  Artifact[]  $artifactList
     */
    public function resolveArtifactPosition(Artifact $targetArtifact, $artifactList): int
    {
        if ($targetArtifact->storedFiles->isNotEmpty()) {
            $minPage = 9999999;
            foreach ($targetArtifact->storedFiles as $storedFile) {
                $minPage = min($minPage, $storedFile->page_number ?? 0);
            }

            return $minPage;
        }

        $artifactsBefore = 0;
        foreach ($artifactList as $refArtifact) {
            if ($targetArtifact->name > $refArtifact->name || $refArtifact->storedFiles) {
                $artifactsBefore++;
            }
        }

        return $artifactsBefore;
    }

    public function afterAllProcessesCompleted(): void
    {
        static::logDebug('All processes completed.');
    }

    /**
     * For group all mode we need to make sure we have 1 single artifact across all processes attached to the task run
     * So lock the task run and resolve the top level artifact
     */
    public function resolveSingletonTaskRunArtifact($artifacts): Artifact
    {
        LockHelper::acquire($this->taskRun);

        try {
            $taskRunArtifact = $this->taskRun->outputArtifacts()->first();

            if (!$taskRunArtifact) {
                $taskRunArtifact = $this->createMergedArtifactFromTopLevel($artifacts);
                $this->taskRun->outputArtifacts()->sync($taskRunArtifact);
                $this->taskRun->updateRelationCounter('outputArtifacts');
            }

            return $taskRunArtifact;
        } finally {
            LockHelper::release($this->taskRun);
        }
    }

    /**
     * @param  Artifact[]|Collection  $artifacts
     */
    public function createMergedArtifactFromTopLevel($artifacts): Artifact
    {
        $topLevels = [];
        foreach ($artifacts as $artifact) {
            $currentLevel = $artifact;

            while ($currentLevel->parent_id) {
                $currentLevel = $currentLevel->parent;
            }

            $topLevels[$currentLevel->id] = $currentLevel;
        }

        $mergedArtifact = app(ArtifactsMergeService::class)->merge($topLevels);

        $mergedArtifact->task_definition_id = $this->taskDefinition->id;
        $mergedArtifact->save();

        return $mergedArtifact;
    }
}
