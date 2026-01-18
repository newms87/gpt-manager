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
        static::logDebug("Prepare $taskDefinition");

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
     *
     * @throws ValidationError
     */
    public static function prepareTaskProcesses(TaskRun $taskRun): array
    {
        static::logDebug("Preparing task processes for $taskRun");

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

        foreach ($schemaAssociations as $schemaAssociation) {
            foreach ($artifactGroups as $artifactsInGroup) {
                $taskProcesses[] = TaskProcessRunnerService::prepare($taskRun, $schemaAssociation, $artifactsInGroup);
            }
        }

        if (!$taskProcesses) {
            static::logDebug("No task processes created. Marking as skipped: $taskRun");
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
        static::logDebug("Continue $taskRun");

        // Always start by acquiring the lock for the task run before checking if it can continue
        // NOTE: This prevents allowing the TaskRun to continue if there was a race condition on failing/stopping the TaskRun
        LockHelper::acquire($taskRun);

        try {
            if (!$taskRun->canContinue()) {
                static::logDebug("TaskRun is $taskRun->status. Skipping execution");

                return;
            }

            if ($taskRun->taskProcesses->isEmpty()) {
                static::logDebug('No task processes found. Skipping execution');

                return;
            }

            if ($taskRun->isStatusPending()) {
                static::logDebug('TaskRun was Pending, starting now...');
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
     * Restart the task run by cloning it.
     * The old run is soft-deleted and linked to the new active run via parent_task_run_id.
     * All previous historical runs are updated to point to the new active run (flat chain structure).
     * Child TaskProcesses remain attached to the soft-deleted old run for history.
     *
     * @return TaskRun The new active task run
     */
    public static function restart(TaskRun $taskRun): TaskRun
    {
        static::logDebug("Restart $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if ($taskRun->isStatusRunning()) {
                throw new ValidationError('TaskRun is currently running, cannot restart');
            }

            // Clone the task run for restart
            $newTaskRun = static::cloneForRestart($taskRun);

            // Sync input artifacts to the new run
            if ($taskRun->workflow_run_id) {
                // For workflow tasks: sync from workflow source nodes
                static::syncInputArtifactsFromWorkflowSourceNodes($newTaskRun);
            } else {
                // For standalone tasks: copy input artifacts from old run
                $inputArtifactIds = $taskRun->inputArtifacts()->pluck('artifacts.id')->toArray();
                if (!empty($inputArtifactIds)) {
                    $newTaskRun->inputArtifacts()->sync($inputArtifactIds);
                    $newTaskRun->updateRelationCounter('inputArtifacts');
                }
            }

            // Link the old run to the new one and soft delete
            $taskRun->parent_task_run_id = $newTaskRun->id;
            $taskRun->save();
            $taskRun->delete();

            // Update all previously soft-deleted historical runs to point to the new active run
            // This maintains a flat chain structure where all history points to the current active run
            TaskRun::onlyTrashed()
                ->where('parent_task_run_id', $taskRun->id)
                ->update(['parent_task_run_id' => $newTaskRun->id]);
        } finally {
            LockHelper::release($taskRun);
        }

        // Dispatch job to prepare task processes for the new run
        (new PrepareTaskProcessJob($newTaskRun))->dispatch();

        return $newTaskRun;
    }

    /**
     * Clone a task run for restart.
     * Creates a new run with reset state, copying task_definition_id, workflow_run_id, workflow_node_id, and task_input_id.
     */
    protected static function cloneForRestart(TaskRun $taskRun): TaskRun
    {
        $newTaskRun = $taskRun->taskDefinition->taskRuns()->create([
            'workflow_run_id'          => $taskRun->workflow_run_id,
            'workflow_node_id'         => $taskRun->workflow_node_id,
            'task_input_id'            => $taskRun->task_input_id,
            'restart_count'            => $taskRun->restart_count + 1,
            'task_process_error_count' => 0,
        ]);

        return $newTaskRun;
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
        foreach ($taskRun->workflowNode->connectionsAsTarget as $connectionAsTarget) {
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
        static::logDebug("Resume $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if (!$taskRun->isStopped() && !$taskRun->isStatusPending()) {
                static::logDebug('TaskRun is not stopped, skipping resume');

                return;
            }

            $taskRun->stopped_at = null;
            $taskRun->save();

            foreach ($taskRun->taskProcesses as $taskProcess) {
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
        static::logDebug("Stop $taskRun");

        LockHelper::acquire($taskRun);

        try {
            if ($taskRun->isStopped()) {
                static::logDebug('TaskRun is already stopped');

                return;
            }

            foreach ($taskRun->taskProcesses as $taskProcess) {
                if (!$taskProcess->isCompleted() && !$taskProcess->isStopped() && !$taskProcess->isFailedAndCannotBeRetried()) {
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

            // Handle edge case: all processes are complete but TaskRun wasn't marked complete
            // This can happen due to race conditions or data migration issues
            if ($taskRun->isStatusRunning() && $taskRun->active_task_processes_count === 0) {
                static::logDebug('All processes complete but TaskRun not marked complete - triggering completion');
                static::afterAllProcessesComplete($taskRun->fresh());
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Called when all processes have finished (active count reaches 0).
     * Calls the runner hook FIRST, then marks complete only if no new processes were created.
     */
    public static function afterAllProcessesComplete(TaskRun $taskRun): void
    {
        static::logDebug("Running afterAllProcessesCompleted hook for $taskRun");

        // 1. Call the runner's hook first (may create new processes)
        $taskRun->getRunner()->afterAllProcessesCompleted();

        static::logDebug('Finished afterAllProcessesCompleted hook');

        // 2. Refresh and check if still no active processes
        $taskRun->refresh();

        if ($taskRun->active_task_processes_count === 0) {
            // No new processes created - now safe to mark complete DIRECTLY
            static::logDebug("No new processes created, marking task run as complete: $taskRun");
            $taskRun->completed_at = now();
            $taskRun->save();
            // This save triggers onComplete() which handles workflow node completion
        } else {
            static::logDebug("New processes created (count: {$taskRun->active_task_processes_count}), not marking complete yet");
        }
        // If count > 0, new processes were created - don't mark complete
    }

    /**
     * When a task run has completed, handle workflow node completion.
     * Called when the task run status changes to completed.
     * Note: The runner's afterAllProcessesCompleted() hook is called BEFORE this
     * by afterAllProcessesComplete() to allow runners to create additional processes.
     */
    public static function onComplete(TaskRun $taskRun): void
    {
        static::logDebug("Completed $taskRun");

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
        foreach ($artifacts as $artifact) {
            // Only accept Artifact instances here. The input should have already converted content into an Artifact
            if (!($artifact instanceof Artifact)) {
                throw new ValidationError('Invalid artifact provided: All artifacts should be an instance of Artifact: ' . (is_object($artifact) ? get_class($artifact) : json_encode($artifact)));
            }
        }
    }

    public static function getTaskRunners(): array
    {
        // Dynamically find all task runners in the app/Services/Task/Runners/* directory
        $taskRunners     = [];
        $taskRunnerFiles = glob(app_path('Services/Task/Runners/*TaskRunner.php'));

        foreach ($taskRunnerFiles as $taskRunnerFile) {
            $className = 'App\\Services\\Task\\Runners\\' . basename($taskRunnerFile, '.php');
            if (class_exists($className)) {
                $taskRunners[$className::RUNNER_NAME] = $className;
            }
        }

        return $taskRunners;
    }
}
