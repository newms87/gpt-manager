<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Workflow\WorkflowRunnerService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;

class TaskRunnerService
{
    use HasDebugLogging;

    /**
     * Prepare a task run for the task definition. Creates a TaskRun object w/ TaskProcess objects
     */
    public static function prepare(TaskDefinition $taskDefinition, $artifacts = []): TaskRun
    {
        $artifacts = collect($artifacts);
        static::log("Prepare $taskDefinition");

        $taskRun = $taskDefinition->taskRuns()->make([
            'status' => WorkflowStatesContract::STATUS_PENDING,
        ]);

        $taskRun->getRunner()->prepareRun();
        $taskRun->save();

        $taskRun->inputArtifacts()->sync($artifacts->pluck('id'));
        $taskRun->updateRelationCounter('inputArtifacts');

        static::prepareTaskProcesses($taskRun, $artifacts);

        return $taskRun;
    }

    /**
     * Prepare task processes for the task run. Each process will receive its own Artifacts / Agent AgentThread
     * based on the input groups and the assigned agents for the TaskDefinition
     * @param TaskRun               $taskRun
     * @param Artifact[]|Collection $artifacts
     * @return array
     * @throws ValidationError
     */
    public static function prepareTaskProcesses(TaskRun $taskRun, $artifacts = []): array
    {
        $artifacts = collect($artifacts);
        static::log("Preparing task processes for $taskRun");

        static::validateArtifacts($artifacts);

        $taskProcesses = [];

        $taskDefinition   = $taskRun->taskDefinition;
        $definitionAgents = $taskDefinition->definitionAgents;

        // NOTE: If there are no agents assigned to the task definition, create an array w/ null entry as a convenience so the loop will create a single process with no agent
        if ($definitionAgents->isEmpty()) {
            $definitionAgents = [null];
        }

        // Split up the artifacts into the groups defined by the task definition
        $artifactGroups = ArtifactsSplitterService::split($taskDefinition->artifact_split_mode ?: '', $artifacts);

        foreach($definitionAgents as $definitionAgent) {
            foreach($artifactGroups as $artifactsInGroup) {
                $taskProcesses[] = TaskProcessRunnerService::prepare($taskRun, $definitionAgent, $artifactsInGroup);
            }
        }

        $taskRun->taskProcesses()->saveMany($taskProcesses);

        return $taskProcesses;
    }

    /**
     * Start a task run for the task definition. This will create a task run and dispatch the task processes
     */
    public static function start(TaskDefinition $taskDefinition, $artifacts = []): TaskRun
    {
        static::log("Start $taskDefinition");

        $taskRun = static::prepare($taskDefinition, $artifacts);

        $taskRun->started_at = now();
        $taskRun->save();

        // Dispatch the take processes to begin the task run
        static::continue($taskRun);

        return $taskRun;
    }

    /**
     * Continue the task run by dispatching any Pending processes and ensuring any timed out processes are flagged
     *
     * NOTE: This will not dispatch any processes if the task run is stopped or failed
     */
    public static function continue(TaskRun $taskRun): void
    {
        static::log("Continue $taskRun");

        // Always start by acquiring the lock for the task run before checking if it can continue
        // NOTE: This prevents allowing the TaskRun to continue if there was a race condition on failing/stopping the TaskRun
        LockHelper::acquire($taskRun);

        try {
            if (!$taskRun->canContinue()) {
                static::log("TaskRun is $taskRun->status. Skipping execution");

                return;
            }

            if ($taskRun->taskProcesses->isEmpty()) {
                static::log("No task processes found. Skipping execution");

                return;
            }

            if ($taskRun->isPending()) {
                static::log("TaskRun was Pending, starting now...");
                // Only start the task run if it is pending
                $taskRun->started_at = now();
                $taskRun->save();
            }

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isCompleted()) {
                    static::log("TaskProcess already Completed. Skipping dispatch: $taskProcess");
                    continue;
                }

                // Only dispatch a task process if it is pending
                if ($taskProcess->isPending()) {
                    TaskProcessRunnerService::dispatch($taskProcess);
                } elseif ($taskProcess->isPastTimeout()) {
                    static::log("TaskProcess $taskProcess timed out, stopping TaskRun $taskRun");
                    $taskProcess->timeout_at = now();
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Restart the task run.
     * This will restart the task run and remove all current task processes and create new task
     * processes. This will also clear all current output artifacts and recreate the input artifacts.
     */
    public static function restart(TaskRun $taskRun): void
    {
        static::log("Restart $taskRun");

        // Always start by acquiring the lock for the task run before checking if it can continue
        // NOTE: This prevents allowing the TaskRun to continue if there was a race condition on failing/stopping the TaskRun
        LockHelper::acquire($taskRun);

        try {
            // Remove the old task processes to make way for the new ones
            $taskRun->taskProcesses()->each(fn(TaskProcess $taskProcess) => $taskProcess->delete());

            // Clear out old output artifacts
            $taskRun->outputArtifacts()->detach();
            $taskRun->updateRelationCounter('outputArtifacts');

            // If this task run is part of a workflow run, collect the output artifacts from the source nodes and replace the current input artifacts
            if ($taskRun->workflow_run_id) {
                $artifacts = $taskRun->workflowRun->collectOutputArtifactsFromSourceNodes($taskRun->workflowNode);
                $taskRun->inputArtifacts()->sync($artifacts->pluck('id')->toArray());
                $taskRun->updateRelationCounter('inputArtifacts');
            }

            $taskRun->stopped_at   = null;
            $taskRun->completed_at = null;
            $taskRun->failed_at    = null;
            $taskRun->started_at   = null;
            $taskRun->save();
            static::prepareTaskProcesses($taskRun, $taskRun->inputArtifacts()->get());
        } finally {
            LockHelper::release($taskRun);
        }

        static::continue($taskRun);
    }

    /**
     * Resume the task run. This will resume all task processes that were stopped
     */
    public static function resume(TaskRun $taskRun): void
    {
        static::log("Resume $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if (!$taskRun->isStopped() && !$taskRun->isPending()) {
                static::log("TaskRun is not stopped, skipping resume");

                return;
            }

            $taskRun->stopped_at = null;
            $taskRun->save();

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isStopped()) {
                    $taskProcess->stopped_at = null;
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }

        static::continue($taskRun);
    }

    /**
     * Stop the task run. This will stop all task processes and prevent any further execution
     */
    public static function stop(TaskRun $taskRun): void
    {
        static::log("Stop $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if ($taskRun->isStopped()) {
                static::log("TaskRun is already stopped");

                return;
            }

            $taskRun->stopped_at = now();
            $taskRun->save();

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isStarted() || $taskProcess->isDispatched()) {
                    $taskProcess->stopped_at = now();
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * When a task run has completed, check to see if there are connections for the given node and execute them
     */
    public static function onComplete(TaskRun $taskRun): void
    {
        static::log("Completed $taskRun");

        $workflowRun = $taskRun->workflowRun;

        if ($workflowRun) {
            WorkflowRunnerService::onNodeComplete($workflowRun, $taskRun->workflowNode);
        }
    }

    /**
     * Ensures all artifacts are instances of Artifact
     */
    public static function validateArtifacts($artifacts): void
    {
        // Validate the artifacts are all Artifact instances
        foreach($artifacts as $artifact) {
            // Only accept Artifact instances here. The input should have already converted content into an Artifact
            if (!($artifact instanceof Artifact)) {
                throw new ValidationError("Invalid artifact provided: All artifacts should be an instance of Artifact: " . (is_object($artifact) ? get_class($artifact) : json_encode($artifact)));
            }
        }
    }
}
