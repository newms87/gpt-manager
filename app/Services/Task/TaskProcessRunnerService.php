<?php

namespace App\Services\Task;

use App\Jobs\WorkflowApiInvocationWebhookJob;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Services\Task\Runners\BaseTaskRunner;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as EloquentCollection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Jobs\Job;
use Newms87\Danx\Services\Error\RetryableErrorChecker;
use Throwable;

class TaskProcessRunnerService
{
    use HasDebugLogging;

    /**
     * Prepare task processes for the task run. Each process will receive its own Artifacts / AgentThread
     * based on the input groups and the schema associations for the TaskDefinition
     */
    public static function prepare(TaskRun $taskRun, ?SchemaAssociation $schemaAssociation = null, $artifacts = [], ?string $operation = null): TaskProcess
    {
        $artifacts = collect($artifacts);
        static::logDebug("Prepare task process for $taskRun w/ " . $artifacts->count() . ' artifacts' . ($schemaAssociation ? ' and schema association ' . $schemaAssociation->id : ''));

        $taskDefinition = $taskRun->taskDefinition;
        $name           = ($schemaAssociation?->schemaFragment?->name ?: $taskDefinition->name);
        $taskProcess    = $taskRun->taskProcesses()->create([
            'name'      => $name,
            'operation' => $operation ?? BaseTaskRunner::OPERATION_DEFAULT,
            'activity'  => "Preparing $name...",
        ]);

        LockHelper::acquire($taskProcess);

        // Only attach TaskProcessJob to task processes (all other job types should not be associated)
        if (Job::$runningJob && Job::$runningJob->name === 'TaskProcessJob') {
            $taskProcess->jobDispatches()->attach(Job::$runningJob);
            $taskProcess->updateRelationCounter('jobDispatches');
        }

        // Associate an identical schema association to the task process
        if ($schemaAssociation) {
            $schemaAssociation->replicate()->forceFill([
                'object_id'   => $taskProcess->id,
                'object_type' => TaskProcess::class,
                'category'    => 'output',
            ])->save();
        }

        try {
            if ($artifacts->isNotEmpty()) {
                $copiedArtifacts = static::copyInputArtifactsForProcesses($taskRun, $artifacts);
                $taskProcess->inputArtifacts()->saveMany($copiedArtifacts);
                $taskProcess->updateRelationCounter('inputArtifacts');
            }

            $taskProcess->getRunner()->prepareProcess();
        } catch (Throwable $throwable) {
            static::logDebug("TaskProcess preparation failed: $taskProcess");
            $taskProcess->incomplete_at = now();
            $taskProcess->save();
            throw $throwable;
        }

        $taskProcess->is_ready = true;
        $taskProcess->save();

        LockHelper::release($taskProcess);
        static::logDebug("Prepared $taskProcess");

        return $taskProcess;
    }

    /**
     * Copy the input artifacts for the task run. This will copy the artifacts to the new task run and assign the
     * task_definition_id
     *
     * @param  Artifact[]|Collection  $artifacts
     */
    public static function copyInputArtifactsForProcesses(TaskRun $taskRun, array|Collection|EloquentCollection $artifacts): array
    {
        $copiedArtifacts = [];

        foreach ($artifacts as $artifact) {
            // If the current task does not own the artifact, we need to copy it
            if ($taskRun->task_definition_id !== $artifact->task_definition_id) {
                $copiedArtifact                       = $artifact->replicate(['parent_artifact_id', 'child_artifacts_count']);
                $copiedArtifact->original_artifact_id = $artifact->id;
                $copiedArtifact->save();

                // Copy the stored files
                $copiedArtifact->storedFiles()->sync($artifact->storedFiles->pluck('id')->toArray());

                if ($artifact->children()->exists()) {
                    // Copy the child artifacts
                    $childCopies = static::copyInputArtifactsForProcesses($taskRun, $artifact->children()->get());
                    $copiedArtifact->assignChildren($childCopies);
                }
                $copiedArtifacts[] = $copiedArtifact;
            } else {
                $copiedArtifacts[] = $artifact;
            }
        }

        return $copiedArtifacts;
    }

    /**
     * Run the task process. This will execute the task process.
     * NOTE: The task runner itself should mark the process as completed when finished successfully
     */
    public static function run(TaskProcess $taskProcess): void
    {
        static::logDebug("Running: $taskProcess");

        // Make sure the task run is not locked before proceeding to prevent running processes before preparation is complete
        LockHelper::acquire($taskProcess->taskRun);
        LockHelper::release($taskProcess->taskRun);

        // Acquire lock for the task process to prevent multiple workers from running it simultaneously
        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canBeRun()) {
                static::logDebug("TaskProcess is $taskProcess->status, skipping execution");

                return;
            }

            // Always make sure the user and team is setup when running a task
            if (!user() && $taskProcess->lastJobDispatch?->user_id) {
                $user   = $taskProcess->lastJobDispatch->user;
                $teamId = $taskProcess->lastJobDispatch?->team_id;
                if ($teamId) {
                    $user->currentTeam = Team::find($teamId);
                }
                auth()->guard()->setUser($user);
            }

            // Associate current job dispatch if we're running in a job context
            $jobDispatch = Job::$runningJob;
            if ($jobDispatch && $jobDispatch->name === 'TaskProcessJob') {
                // track the most recent dispatch for easier referencing
                $taskProcess->last_job_dispatch_id = $jobDispatch->id;

                // Associate all job dispatches with the task process for logging purposes
                $taskProcess->jobDispatches()->attach($jobDispatch->id);
                $taskProcess->updateRelationCounter('jobDispatches');
            }

            $taskProcess->started_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Run the task process
        try {
            $taskProcess->getRunner()->run();
            static::logDebug("TaskProcess finished running: $taskProcess");
        } catch (Throwable $throwable) {
            $maxRetries = $taskProcess->taskRun?->taskDefinition?->max_process_retries ?? 0;
            $canRetry   = $taskProcess->canBeRetried();
            $retryInfo  = $canRetry
                ? " (will retry: {$taskProcess->restart_count}/{$maxRetries})"
                : " (retries exhausted: {$taskProcess->restart_count}/{$maxRetries})";
            static::logDebug("TaskProcess failed: $taskProcess" . $retryInfo . "\n" . $throwable->getMessage());

            if (RetryableErrorChecker::isRetryable($throwable)) {
                // Transient error - can be retried
                $taskProcess->incomplete_at = now();
            } else {
                // Permanent error - mark as failed immediately
                $taskProcess->failed_at = now();
            }

            $taskProcess->save();
            throw $throwable;
        }
    }

    /**
     * Run the task process. This will execute the task process.
     * NOTE: The task runner itself should mark the process as completed when finished successfully
     */
    public static function eventTriggered(TaskProcessListener $taskProcessListener): void
    {
        static::logDebug("Event Triggered $taskProcessListener");

        $taskProcess = $taskProcessListener->taskProcess;

        // Run the task process
        try {
            $taskProcess->getRunner()->eventTriggered($taskProcessListener);

            $invocation = $taskProcess->taskRun->workflowRun?->workflowApiInvocation;

            if ($invocation) {
                (new WorkflowApiInvocationWebhookJob($invocation))->dispatch();
            }
            static::logDebug("TaskProcess finished handling event: $taskProcessListener");
        } catch (Throwable $throwable) {
            static::logDebug("TaskProcess event handler failed: $taskProcess");
            $taskProcess->incomplete_at = now();
            $taskProcess->save();
            throw $throwable;
        }
    }

    /**
     * Restart the task process by cloning it.
     * The old process is soft-deleted and linked to the new active process via parent_task_process_id.
     * All previous historical processes are updated to point to the new active process (flat chain structure).
     *
     * @return TaskProcess The new active process
     */
    public static function restart(TaskProcess $taskProcess): TaskProcess
    {
        static::logDebug("Restart $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if ($taskProcess->isStatusRunning()) {
                throw new ValidationError('TaskProcess is currently running, cannot restart');
            }

            // Halt any running agent threads
            static::halt($taskProcess);

            // Clone the process for restart
            $newProcess = static::cloneForRestart($taskProcess);

            // Link the old process to the new one and soft delete
            $taskProcess->parent_task_process_id = $newProcess->id;
            $taskProcess->save();
            $taskProcess->delete();

            // Update all previously soft-deleted historical processes to point to the new active process
            // This maintains a flat chain structure where all history points to the current active process
            TaskProcess::onlyTrashed()
                ->where('parent_task_process_id', $taskProcess->id)
                ->update(['parent_task_process_id' => $newProcess->id]);
        } finally {
            LockHelper::release($taskProcess);
        }

        // Trigger dispatcher to pick up the new process
        TaskProcessDispatcherService::dispatchForTaskRun($newProcess->taskRun);

        return $newProcess;
    }

    /**
     * Clone a task process for restart.
     * Creates a new process with reset state, syncs input artifacts, and copies schema association.
     */
    protected static function cloneForRestart(TaskProcess $taskProcess): TaskProcess
    {
        // Create new process with reset state
        $newProcess = $taskProcess->taskRun->taskProcesses()->create([
            'name'             => $taskProcess->name,
            'operation'        => $taskProcess->operation,
            'activity'         => "Restarting {$taskProcess->name}...",
            'is_ready'         => true,
            'restart_count'    => $taskProcess->restart_count + 1,
            'percent_complete' => 0,
        ]);

        // Sync input artifacts (reference same artifacts, not copy)
        $inputArtifactIds = $taskProcess->inputArtifacts()->pluck('artifacts.id')->toArray();
        if (!empty($inputArtifactIds)) {
            $newProcess->inputArtifacts()->sync($inputArtifactIds);
            $newProcess->updateRelationCounter('inputArtifacts');
        }

        // Copy schema association if exists
        $schemaAssociation = $taskProcess->outputSchemaAssociation;
        if ($schemaAssociation) {
            $schemaAssociation->replicate()->forceFill([
                'object_id'   => $newProcess->id,
                'object_type' => TaskProcess::class,
                'category'    => 'output',
            ])->save();
        }

        return $newProcess;
    }

    /**
     * Resume the task process. This will resume the task process if it was stopped
     */
    public static function resume(TaskProcess $taskProcess): void
    {
        static::logDebug("Resume $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canResume()) {
                static::logDebug('TaskProcess is not in a resumable state, skipping resume');

                return;
            }

            $taskProcess->stopped_at    = null;
            $taskProcess->failed_at     = null;
            $taskProcess->incomplete_at = null;
            $taskProcess->timeout_at    = null;
            // NOTE: we must reset the started_at and completed_at flag so the task process can be re-run
            $taskProcess->started_at   = null;
            $taskProcess->completed_at = null;
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Trigger dispatcher to pick up this restarted process
        TaskProcessDispatcherService::dispatchForTaskRun($taskProcess->taskRun);
    }

    /**
     * Stop the task process. This will prevent the task process from executing further
     */
    public static function stop(TaskProcess $taskProcess): void
    {
        static::logDebug("Stop $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if ($taskProcess->isStopped()) {
                static::logDebug('TaskProcess is already stopped');

                return;
            }

            $taskProcess->stopped_at = now();
            $taskProcess->save();

            static::halt($taskProcess);
        } finally {
            LockHelper::release($taskProcess);
        }
    }

    /**
     * Process the completion of a task process.
     * This will mark the task process completed and continue the task run
     */
    public static function complete(TaskProcess $taskProcess): void
    {
        static::logDebug('TaskProcess completed w/ ' . $taskProcess->outputArtifacts()->count() . " artifacts: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            // Mark this task process as completed
            $taskProcess->failed_at     = null;
            $taskProcess->incomplete_at = null;
            $taskProcess->stopped_at    = null;
            $taskProcess->timeout_at    = null;
            $taskProcess->completed_at  = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Dispatch more pending processes if available
        TaskProcessDispatcherService::dispatchForTaskRun($taskProcess->taskRun);
    }

    /**
     * Handle the timeout of a task process.
     * The task process will be restarted automatically if the max_process_retries has not been reached
     *
     * @return bool True if the process was restarted (and dispatched), false otherwise
     */
    public static function handleTimeout(TaskProcess $taskProcess): bool
    {
        $canRetry = false;

        LockHelper::acquire($taskProcess);

        try {
            // Can only be timed out if it is in one of these states
            if (!$taskProcess->isStatusPending() && !$taskProcess->isStatusRunning()) {
                return false;
            }

            static::logDebug("Task process timed out: $taskProcess");
            $taskProcess->timeout_at = now();
            $taskProcess->save();

            static::halt($taskProcess);

            $canRetry = $taskProcess->canBeRetried();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Restart outside the lock since restart() acquires its own lock
        if ($canRetry) {
            static::restart($taskProcess);

            return true; // Process was restarted and dispatched
        }

        return false; // Process was not restarted
    }

    /**
     * Stops any running threads for the task process
     */
    public static function halt(TaskProcess $taskProcess): void
    {
        if ($taskProcess->agentThread?->isRunning()) {
            $taskProcess->agentThread->currentRun->failed_at = now();
            $taskProcess->agentThread->currentRun->save();
        }
    }
}
