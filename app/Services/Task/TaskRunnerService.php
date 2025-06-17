<?php

namespace App\Services\Task;

use App\Jobs\PrepareTaskProcessJob;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Workflow\WorkflowRunnerService;
use App\Traits\HasDebugLogging;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;

class TaskRunnerService
{
    use HasDebugLogging;

    /**
     * Prepare a task run for the task definition. Creates a TaskRun object w/ TaskProcess objects
     */
    public static function prepareTaskRun(TaskDefinition $taskDefinition): TaskRun
    {
        static::log("Prepare $taskDefinition");

        $taskRun = $taskDefinition->taskRuns()->make([
            'status' => WorkflowStatesContract::STATUS_PENDING,
        ]);

        $taskRun->getRunner()->prepareRun();
        $taskRun->save();

        return $taskRun;
    }

    /**
     * Prepare task processes for the task run. Each process will receive its own Artifacts / AgentThread
     * based on the input groups and the assigned schema associations for the TaskDefinition
     * @param TaskRun $taskRun
     * @return array
     * @throws ValidationError
     */
    public static function prepareTaskProcesses(TaskRun $taskRun): array
    {
        static::log("Preparing task processes for $taskRun");

        $taskProcesses = [];

        $taskDefinition     = $taskRun->taskDefinition;
        $schemaAssociations = $taskDefinition->schemaAssociations;

        // NOTE: If there are no schema associations assigned to the task definition, create an array w/ null entry as a convenience so the loop will create a single process with no schema
        if ($taskDefinition->isTextResponse() || $schemaAssociations->isEmpty()) {
            $schemaAssociations = [null];
        }

        $query = $taskRun->inputArtifacts();

        $maxLevel = max($taskDefinition->input_artifact_levels ?: [0]);

        // Eager load the children of the artifacts to avoid N+1 queries
        if ($maxLevel > 0) {
            $query->with(implode('.', array_fill(0, $maxLevel, 'children')));
        }

        $artifacts = $query->get();


        // Split up the artifacts into the groups defined by the task definition
        $artifactGroups = ArtifactsSplitterService::split($taskDefinition->input_artifact_mode ?: '', $artifacts, $taskDefinition->input_artifact_levels);

        foreach($schemaAssociations as $schemaAssociation) {
            foreach($artifactGroups as $artifactsInGroup) {
                $taskProcesses[] = TaskProcessRunnerService::prepare($taskRun, $schemaAssociation, $artifactsInGroup);
            }
        }

        if (!$taskProcesses) {
            $taskRun->skipped_at = now();
            $taskRun->save();
        }

        $taskRun->taskProcesses()->saveMany($taskProcesses);

        return $taskProcesses;
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

            if ($taskRun->isStatusPending()) {
                static::log("TaskRun was Pending, starting now...");
                // Only start the task run if it is pending
                $taskRun->started_at = now();
                $taskRun->save();
            }

            // Use the dispatcher service to handle process dispatching
            TaskProcessDispatcherService::dispatchForTaskRun($taskRun);
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

            // Clear out old input / output artifacts
            $taskRun->clearOutputArtifacts();
            $taskRun->clearInputArtifacts();

            // Reset task run statuses
            $taskRun->stopped_at   = null;
            $taskRun->completed_at = null;
            $taskRun->failed_at    = null;
            $taskRun->started_at   = null;
            $taskRun->skipped_at   = null;
            $taskRun->save();

            // If this task run is part of a workflow run, collect the output artifacts from the source nodes and replace the current input artifacts
            if ($taskRun->workflow_run_id) {
                static::syncInputArtifactsFromWorkflowSourceNodes($taskRun);
            }

            (new PrepareTaskProcessJob($taskRun))->dispatch();
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Sync the input artifacts for a task run that is part of a workflow run.
     * Uses the output artifacts of all source nodes in the workflow for the task run's target node, and syncs them as
     * the input artifacts for this task run. This will also indicate the source task definition for these artifacts so
     * the task run knows the relationship w/ these artifacts and can apply filters, etc.
     */
    public static function syncInputArtifactsFromWorkflowSourceNodes(TaskRun $taskRun): void
    {
        if (!$taskRun->workflowRun || !$taskRun->workflowNode) {
            throw new Exception("Sync input artifacts error: task run not part of a workflow run or does not have a workflow node: $taskRun");
        }

        // Loop through all the source nodes of the target node to gather the output artifacts of each one
        foreach($taskRun->workflowNode->connectionsAsTarget as $connectionAsTarget) {
            $outputArtifacts = $taskRun->workflowRun->collectOutputArtifactsForNode($connectionAsTarget->sourceNode);

            // Indicate the source task definition for these artifacts so the task run knows the relationship w/ these artifacts and can apply filters, etc.
            $taskRun->addInputArtifacts($outputArtifacts);
        }
    }

    /**
     * Resume the task run. This will resume all task processes that were stopped
     */
    public static function resume(TaskRun $taskRun): void
    {
        static::log("Resume $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if (!$taskRun->isStopped() && !$taskRun->isStatusPending()) {
                static::log("TaskRun is not stopped, skipping resume");

                return;
            }

            $taskRun->stopped_at = null;
            $taskRun->save();

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isStatusStopped()) {
                    $taskProcess->stopped_at           = null;
                    $taskProcess->timeout_at           = null;
                    $taskProcess->started_at           = null;
                    $taskProcess->last_job_dispatch_id = null;
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

            foreach($taskRun->taskProcesses as $taskProcess) {
                if (!$taskProcess->isCompleted() && !$taskProcess->isStopped() && !$taskProcess->isFailed() && !$taskProcess->isTimedout()) {
                    $taskProcess->stopped_at = now();
                    $taskProcess->save();
                }
            }

            // Double-check our state in case we're out of sync
            $taskRun->checkProcesses()->computeStatus()->save();

            // If we're suppose to stop
            if ($taskRun->isStatusPending() && $taskRun->taskProcesses->isEmpty()) {
                $taskRun->stopped_at = now();
                $taskRun->save();
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
        static::log("Running afterAllProcessesCompleted for $taskRun");

        $taskRun->getRunner()->afterAllProcessesCompleted();

        static::log("Finished afterAllProcessesCompleted");

        // If additional task processes were created by the task runner, skip completing the task run and starting the next nodes in the workflow
        if (!$taskRun->refresh()->isCompleted()) {
            return;
        }

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

    public static function getTaskRunners(): array
    {
        // Dynamically find all task runners in the app/Services/Task/Runners/* directory
        $taskRunners     = [];
        $taskRunnerFiles = glob(app_path('Services/Task/Runners/*TaskRunner.php'));

        foreach($taskRunnerFiles as $taskRunnerFile) {
            $className = 'App\\Services\\Task\\Runners\\' . basename($taskRunnerFile, '.php');
            if (class_exists($className)) {
                $taskRunners[$className::RUNNER_NAME] = $className;
            }
        }

        return $taskRunners;
    }
}
