<?php

namespace App\Services\Task;

use App\Jobs\ExecuteTaskProcessJob;
use App\Jobs\WorkflowApiInvocationWebhookJob;
use App\Models\Task\TaskDefinitionAgent;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Models\Job\JobDispatch;
use Throwable;

class TaskProcessRunnerService
{
    use HasDebugLogging;

    /**
     * Prepare task processes for the task run. Each process will receive its own Artifacts / Agent AgentThread
     * based on the input groups and the assigned agents for the TaskDefinition
     */
    public static function prepare(TaskRun $taskRun, TaskDefinitionAgent $taskDefinitionAgent = null, $artifacts = []): TaskProcess
    {
        $artifacts = collect($artifacts);
        static::log("Prepare task process for $taskRun w/ " . $artifacts->count() . " artifacts");

        $taskDefinition = $taskRun->taskDefinition;
        $name           = ($taskDefinitionAgent?->agent->name ?: $taskDefinition->name);
        $taskProcess    = $taskRun->taskProcesses()->create([
            'name'                     => $name,
            'activity'                 => "Preparing $name...",
            'status'                   => WorkflowStatesContract::STATUS_PENDING,
            'task_definition_agent_id' => $taskDefinitionAgent?->id,
        ]);

        if ($artifacts->isNotEmpty()) {
            $taskProcess->inputArtifacts()->saveMany($artifacts);
            $taskProcess->updateRelationCounter('inputArtifacts');
        }

        $taskProcess->getRunner()->prepareProcess();

        static::log("Prepared $taskProcess");

        return $taskProcess;
    }

    /**
     * Dispatch a task process to be executed by the job queue
     */
    public static function dispatch(TaskProcess $taskProcess): ?JobDispatch
    {
        static::log("Dispatch $taskProcess");

        // associate job dispatch before dispatching in case of synchronous job execution
        $job = (new ExecuteTaskProcessJob($taskProcess));

        // Associate JobDispatch to TaskProcess
        $jobDispatch = $job->getJobDispatch();
        if ($jobDispatch) {
            // track the most recent dispatch for easier referencing
            $taskProcess->last_job_dispatch_id = $jobDispatch->id;
            $taskProcess->save();

            // Associate all job dispatches with the task process for logging purposes
            $taskProcess->jobDispatches()->attach($jobDispatch->id);
            $taskProcess->updateRelationCounter('jobDispatches');
        }

        // Dispatch the job
        $job->dispatch();

        return $jobDispatch;
    }

    /**
     * Run the task process. This will execute the task process.
     * NOTE: The task runner itself should mark the process as completed when finished successfully
     */
    public static function run(TaskProcess $taskProcess): void
    {
        static::log("Running: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canBeRun()) {
                static::log("TaskProcess is $taskProcess->status, skipping execution");

                return;
            }

            $taskProcess->started_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Run the task process
        try {
            $taskProcess->getRunner()->run();
            static::log("TaskProcess finished running: $taskProcess");
        } catch(Throwable $throwable) {
            static::log("TaskProcess failed: $taskProcess");
            $taskProcess->failed_at = now();
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
        static::log("Event Triggered $taskProcessListener");

        $taskProcess = $taskProcessListener->taskProcess;

        // Run the task process
        try {
            $taskProcess->getRunner()->eventTriggered($taskProcessListener);
            
            $invocation = $taskProcess->taskRun->workflowRun?->workflowApiInvocation;

            if ($invocation) {
                (new WorkflowApiInvocationWebhookJob($invocation))->dispatch();
            }
            static::log("TaskProcess finished handling event: $taskProcessListener");
        } catch(Throwable $throwable) {
            static::log("TaskProcess event handler failed: $taskProcess");
            $taskProcess->failed_at = now();
            $taskProcess->save();
            throw $throwable;
        }
    }

    /**
     * Restart the task process. This will reset the task process to its initial state and dispatch it for execution
     */
    public static function restart(TaskProcess $taskProcess): void
    {
        static::log("Restart $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if ($taskProcess->isRunning()) {
                throw new ValidationError("TaskProcess is currently running, cannot restart");
            }
            $taskProcess->outputArtifacts()->delete();
            $taskProcess->updateRelationCounter('outputArtifacts');

            $taskProcess->stopped_at = null;
            $taskProcess->failed_at  = null;
            $taskProcess->timeout_at = null;
            // NOTE: we must reset the started_at and completed_at flag so the task process can be re-run
            $taskProcess->started_at       = null;
            $taskProcess->completed_at     = null;
            $taskProcess->percent_complete = 0;
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        static::dispatch($taskProcess);
    }

    /**
     * Resume the task process. This will resume the task process if it was stopped
     */
    public static function resume(TaskProcess $taskProcess): void
    {
        static::log("Resume $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canResume()) {
                static::log("TaskProcess is not in a resumable state, skipping resume");

                return;
            }

            $taskProcess->stopped_at = null;
            $taskProcess->failed_at  = null;
            $taskProcess->timeout_at = null;
            // NOTE: we must reset the started_at and completed_at flag so the task process can be re-run
            $taskProcess->started_at   = null;
            $taskProcess->completed_at = null;
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        static::dispatch($taskProcess);
    }

    /**
     * Stop the task process. This will prevent the task process from executing further
     */
    public static function stop(TaskProcess $taskProcess): void
    {
        static::log("Stop $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if ($taskProcess->isStopped()) {
                static::log("TaskProcess is already stopped");

                return;
            }

            $taskProcess->stopped_at = now();
            $taskProcess->save();
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
        static::log("TaskProcess completed w/ " . $taskProcess->outputArtifacts()->count() . " artifacts: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            $taskProcess->completed_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }
    }
}
