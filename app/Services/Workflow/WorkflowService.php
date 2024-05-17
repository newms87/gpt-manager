<?php

namespace App\Services\Workflow;

use App\Jobs\RunWorkflowTaskJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use Flytedan\DanxLaravel\Helpers\LockHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowService
{
    /**
     * Begin the Workflow by creating all the jobs for the Workflow Run
     *
     * @throws Throwable
     */
    public static function start(WorkflowRun $workflowRun): void
    {
        $workflowRun->started_at = now();
        $workflowRun->save();

        $workflowJobs = $workflowRun->workflow->workflowJobs()->get();
        foreach($workflowJobs as $workflowJob) {
            Log::debug("Creating run for Workflow Job $workflowJob->name ($workflowJob->id)");
            $workflowRun->workflowJobRuns()->create([
                'workflow_job_id' => $workflowJob->id,
            ]);
        }

        static::dispatchPendingWorkflowJobs($workflowRun);
    }

    /**
     * Dispatch any pending workflow run jobs that have their dependencies met
     *
     * @param WorkflowRun $workflowRun
     * @return void
     * @throws Throwable
     */
    public static function dispatchPendingWorkflowJobs(WorkflowRun $workflowRun): void
    {
        /** @var WorkflowJobRun[][]|Collection $workflowJobRunsByStatus */
        $workflowJobRunsByStatus = $workflowRun->workflowJobRuns()->get()->keyBy('workflow_job_id')->groupBy('status');

        if ($workflowJobRunsByStatus->has(WorkflowRun::STATUS_FAILED)) {
            Log::warning("Workflow Run has failed jobs, stopping dispatch");

            return;
        }

        /** @var WorkflowJobRun[]|Collection $completedJobRuns */
        $completedJobRuns = $workflowJobRunsByStatus->get(WorkflowRun::STATUS_COMPLETED);
        $completedIds     = $completedJobRuns?->pluck('workflow_job_id')->toArray() ?? [];
        /** @var WorkflowJobRun[]|Collection $pendingJobRuns */
        $pendingJobRuns = $workflowJobRunsByStatus->get(WorkflowRun::STATUS_PENDING) ?? [];

        foreach($pendingJobRuns as $pendingJobRun) {
            $workflowJob     = $pendingJobRun->workflowJob;
            $dependsOnJobIds = $workflowJob->depends_on ?: [];

            // If the job has not been completed, then we cannot dispatch this task
            if (count(array_diff($dependsOnJobIds, $completedIds)) > 0) {
                Log::debug("Job {$workflowJob->name} has dependencies that have not yet completed");
                continue;
            }

            $assignments = $workflowJob->workflowAssignments()->get();
            foreach($assignments as $assignment) {
                $pendingTask = $pendingJobRun->tasks()->create([
                    'user_id'                => user()->id,
                    'workflow_job_id'        => $workflowJob->id,
                    'workflow_assignment_id' => $assignment->id,
                    'status'                 => WorkflowTask::STATUS_PENDING,
                ]);

                (new RunWorkflowTaskJob($pendingTask))->dispatch();
            }
        }
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
        Log::debug("Task {$task->id} has finished: {$task->status}");
        $workflowJobRun = $task->workflowJobRun;

        // If the workflow run has failed, stop processing
        if ($workflowJobRun->failed_at) {
            Log::debug("Workflow Job Run has already failed, stopping dispatch");

            return;
        }

        // Make sure race conditions don't allow a Job to be marked completed when another task failed
        LockHelper::acquire($workflowJobRun);

        $isFinished = $workflowJobRun->remainingTasks()->count() === 0;

        // If the task completed successfully, then save the artifact and mark the job as completed if there are no more tasks
        if ($task->isComplete()) {
            Log::debug("Setting artifact");

            // Save the artifact from the completed task
            $workflowJobRun->artifact()->save($task->artifact()->first());

            // If we have finished all tasks in the workflow job run, then mark job as completed and notify the workflow run
            if ($isFinished) {
                // The workflow Job Run has completed successfully. Save the artifact from the completed task and notify the Workflow Run
                $workflowJobRun->completed_at = now();
                $workflowJobRun->save();
            }
        } else {
            $workflowJobRun->failed_at = now();
            $workflowJobRun->save();
        }

        LockHelper::release($workflowJobRun);

        if ($isFinished) {
            WorkflowService::workflowJobRunFinished($workflowJobRun);
        }
    }

    /**
     * A Workflow Job Run has finished. Set the Workflow Run as failed if the Workflow Job Run failed.
     * If the Workflow Job Run succeeded and the Workflow has no more jobs running, then mark the Workflow Run it as
     * completed. Otherwise, dispatch the next set of Workflow Job Runs if there are any remaining to be dispatched that
     * have their dependencies met.
     *
     * @param WorkflowJobRun $workflowJobRun
     * @return void
     * @throws Throwable
     */
    public static function workflowJobRunFinished(WorkflowJobRun $workflowJobRun): void
    {
        Log::debug("Workflow Job Run {$workflowJobRun->id} has finished: {$workflowJobRun->status}");
        $workflowRun = $workflowJobRun->workflowRun;

        // If the workflow run has failed, stop processing
        if ($workflowRun->failed_at) {
            Log::debug("Workflow Run has already failed, stopping dispatch");

            return;
        }

        // Make sure no race conditions accidentally complete a workflow when another one failed or double dispatch jobs
        LockHelper::acquire($workflowRun);

        if ($workflowJobRun->isComplete()) {
            // Save the artifact from the completed task
            $workflowRun->artifact()->save($workflowJobRun->artifact()->first());

            // If we have completed all Workflow Job Runs in the workflow run, then mark the workflow run as completed
            if ($workflowRun->remainingJobRuns()->doesntExist()) {
                $workflowRun->completed_at = now();
                $workflowRun->save();
            } else {
                // Dispatch the next set of Workflow Job Runs
                WorkflowService::dispatchPendingWorkflowJobs($workflowRun);
            }
        } else {
            // If the Workflow Job Run failed, then mark the Workflow Run as failed
            $workflowRun->failed_at = now();
            $workflowRun->save();
        }

        LockHelper::release($workflowRun);
    }
}
