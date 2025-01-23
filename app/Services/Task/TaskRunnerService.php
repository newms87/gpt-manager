<?php

namespace App\Services\Task;

use App\Jobs\ExecuteTaskProcessJob;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Models\Job\JobDispatch;

class TaskRunnerService
{
    /**
     * Prepare a task run for the task definition. Creates a TaskRun object w/ TaskProcess objects
     */
    public static function prepareTaskRun(TaskDefinition $taskDefinition, array|Collection $artifacts = [])
    {
        $taskRun = $taskDefinition->taskRuns()->create([
            'status' => TaskProcess::STATUS_PENDING,
        ]);

        static::prepareTaskProcesses($taskRun);

        return $taskRun;
    }

    /**
     * Prepare task processes for the task run. Each process will receive its own Artifacts / Agent Thread
     * based on the input groups and the assigned agents for the TaskDefinition
     */
    public static function prepareTaskProcesses(TaskRun $taskRun): array
    {
        $taskProcesses = [];

        $taskProcesses[] = $taskRun->taskProcesses()->create([
            'status' => TaskProcess::STATUS_PENDING,
        ]);

        $taskRun->taskProcesses()->saveMany($taskProcesses);

        return $taskProcesses;
    }

    /**
     * Continue the task run by executing the next set of processes.
     * NOTE: This will not dispatch the next processes if the task run is stopped or failed
     */
    public static function continue(TaskRun $taskRun): void
    {
        Log::debug("Continuing $taskRun");

        // Always start by acquiring the lock for the task run before checking if it can continue
        // NOTE: This prevents allowing the TaskRun to continue if there was a race condition on failing/stopping the TaskRun
        LockHelper::acquire($taskRun);

        if (!$taskRun->canContinue()) {
            Log::debug("TaskRun is $taskRun->status, skipping execution");

            return;
        }

        try {
            if ($taskRun->isPending()) {
                Log::debug("Starting TaskRun...");
                // Only start the task run if it is pending
                $taskRun->started_at = now();
                $taskRun->save();
            }

            foreach($taskRun->taskProcesses as $taskProcess) {
                if ($taskProcess->isCompleted()) {
                    continue;
                }

                // Only dispatch a task process if it is pending
                if ($taskProcess->isPending()) {
                    static::dispatchProcess($taskProcess);
                } elseif ($taskProcess->isPastTimeout()) {
                    Log::debug("TaskProcess $taskProcess timed out, stopping TaskRun $taskRun");
                    $taskProcess->timeout_at = now();
                    $taskProcess->save();
                }
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Dispatch a task process to be executed by the job queue
     */
    public static function dispatchProcess(TaskProcess $taskProcess): ?JobDispatch
    {
        Log::debug("Dispatching: $taskProcess");

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
        }

        // Dispatch the job
        $job->dispatch();

        return $jobDispatch;
    }

    /**
     * Run the task process. This will execute the task process and mark it as completed when finished
     */
    public static function runProcess(TaskProcess $taskProcess): void
    {
        Log::debug("Running: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            if (!$taskProcess->canBeRun()) {
                Log::debug("TaskProcess is $taskProcess->status, skipping execution");

                return;
            }

            $taskProcess->started_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Run the task process
        $runner = $taskProcess->taskRun->getRunner();
        $runner->run($taskProcess);
    }

    /**
     * Process the completion of a task process.
     * This will mark the task process completed and continue the task run
     */
    public static function processCompleted(TaskProcess $taskProcess): void
    {
        Log::debug("TaskProcess completed: $taskProcess");

        LockHelper::acquire($taskProcess);

        try {
            $taskProcess->completed_at = now();
            $taskProcess->save();
        } finally {
            LockHelper::release($taskProcess);
        }

        // Continue the task run if there are more processes to run
        static::continue($taskProcess->taskRun);
    }
}
