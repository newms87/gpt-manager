<?php

namespace App\Services\Workflow;

use App\Jobs\RunWorkflowTaskJob;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Models\Audit\ErrorLog;
use Throwable;

class WorkflowTaskService
{
    /**
     * Run a Workflow Task, produce an artifact and notify the Workflow of completion / failure
     *
     * @param WorkflowTask $workflowTask
     * @return void
     * @throws Throwable
     */
    public static function start(WorkflowTask $workflowTask): void
    {
        Log::debug("$workflowTask started");

        // Note: We must lock before checking pending status in case a race condition caused someone else to beat us to this task
        LockHelper::acquire($workflowTask);

        if ($workflowTask->status !== WorkflowRun::STATUS_PENDING) {
            Log::debug("$workflowTask has already been run");
            LockHelper::release($workflowTask);

            return;
        }

        $workflowTask->started_at = now();
        $workflowTask->save();

        try {
            // Run the workflow tool for the task
            $tool = $workflowTask->workflowJob->getWorkflowTool();

            Log::debug("$workflowTask running $tool");
            $tool->runTask($workflowTask);
            Log::debug("$workflowTask completed $tool");

            $workflowTask->completed_at = now();
            $workflowTask->save();
        } catch(Throwable $e) {
            ErrorLog::logException(ErrorLog::ERROR, $e);
            $workflowTask->failed_at = now();
            $workflowTask->save();
        } finally {
            LockHelper::release($workflowTask);
        }

        // Notify the Workflow our task is finished
        static::taskFinished($workflowTask);
    }

    /**
     * A Workflow Task has finished. Set the Workflow Job Run as failed if the task is not complete.
     * If the Workflow Job Run has no more tasks to complete, then mark it as completed.
     * If Completed, notify the Workflow Run that the Workflow Job has finished.
     *
     * @param WorkflowTask $task
     * @return void
     * @throws Throwable
     */
    public static function taskFinished(WorkflowTask $task): void
    {
        Log::debug("$task finished running");
        $workflowJobRun = $task->workflowJobRun;

        try {
            // If the workflow run has failed, stop processing
            if ($workflowJobRun->failed_at) {
                Log::debug("$workflowJobRun has already failed, stopping dispatch");

                return;
            }

            // Make sure race conditions don't allow a Job to be marked completed when another task failed
            LockHelper::acquire($workflowJobRun);

            $isFinished = $workflowJobRun->remainingTasks()->count() === 0;

            // If the task completed successfully, then save the artifact and mark the job as completed if there are no more tasks
            if ($task->isComplete()) {
                // Save the artifact from the completed task
                $artifact = $task->artifacts()->first();

                if ($artifact) {
                    $workflowJobRun->artifacts()->syncWithoutDetaching($artifact);
                    Log::debug("$workflowJobRun attached $artifact");
                }

                // If we have finished all tasks in the workflow job run, then mark job as completed and notify the workflow run
                if ($isFinished) {
                    // The workflow Job Run has completed successfully. Save the artifact from the completed task and notify the Workflow Run
                    $workflowJobRun->completed_at = now();
                    $workflowJobRun->save();
                }
            } else {
                Log::debug("$workflowJobRun has failed");
                $workflowJobRun->failed_at = now();
                $workflowJobRun->save();
                $workflowJobRun->workflowRun->failed_at = now();
                $workflowJobRun->workflowRun->save();
            }

            LockHelper::release($workflowJobRun);

            if ($isFinished) {
                WorkflowService::workflowJobRunFinished($workflowJobRun);
            }
        } catch(Throwable $e) {
            // If there was an exception while processing the next dispatch, then mark the task and the workflow as failed
            ErrorLog::logException(ErrorLog::ERROR, $e);
            $task->failed_at = now();
            $task->save();
            $workflowJobRun->failed_at = now();
            $workflowJobRun->save();
            $workflowJobRun->workflowRun->failed_at = now();
            $workflowJobRun->workflowRun->save();
        }
    }

    /**
     * @param WorkflowRun $workflowRun
     * @return void
     * @throws Throwable
     */
    public static function dispatchPendingWorkflowTasks(WorkflowRun $workflowRun): void
    {
        $jobsToDispatch = [];

        foreach($workflowRun->runningJobRuns()->get() as $pendingJobRun) {
            LockHelper::acquire($pendingJobRun);

            try {
                $pendingTasks = $pendingJobRun->pendingTasks()->get();

                // If there are no tasks, the job is automatically completed
                // NOTE: we check that there really are no tasks (instead of just pending tasks) in case there were tasks run. In that case it should not be auto-completed
                //       Tasks may have just completed or failed and due to race conditions not properly updated yet.
                if ($pendingTasks->isEmpty() && $pendingJobRun->tasks()->doesntExist()) {
                    $pendingJobRun->completed_at = now();
                    $pendingJobRun->save();
                    Log::debug("$pendingJobRun has no pending tasks, marking job as complete");
                    WorkflowService::workflowJobRunFinished($pendingJobRun);
                    continue;
                }

                // Dispatch an async job for each pending task in the workflow job
                foreach($pendingJobRun->pendingTasks()->get() as $pendingTask) {
                    Log::debug("$pendingJobRun dispatching $pendingTask");
                    if ($pendingTask->job_dispatch_id) {
                        Log::debug("Already dispatched");
                        continue;
                    }

                    $job                          = (new RunWorkflowTaskJob($pendingTask));
                    $pendingTask->job_dispatch_id = $job->getJobDispatch()?->id;
                    $pendingTask->save();
                    $jobsToDispatch[] = $job;
                }
            } finally {
                LockHelper::release($pendingJobRun);
            }
        }

        // Dispatch all the jobs after releasing the locks
        foreach($jobsToDispatch as $job) {
            $job->dispatch();
        }
    }
}
