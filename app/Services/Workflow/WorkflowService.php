<?php

namespace App\Services\Workflow;

use App\Jobs\RunWorkflowTaskJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
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
        $pendingJobRuns = $workflowJobRunsByStatus->get(WorkflowRun::STATUS_PENDING);

        foreach($pendingJobRuns as $pendingJobRun) {
            $workflowJob     = $pendingJobRun->workflowJob;
            $dependsOnJobIds = $workflowJob->depends_on ?: [];

            // If the job has not been completed, then we cannot dispatch this task
            if (count(array_diff($dependsOnJobIds, $completedIds)) > 0) {
                Log::debug("Job {$workflowJob->name} has dependencies that have not yet completed");
                continue;
            }

            Log::debug("Dispatching Workflow Job $pendingJobRun->id");

            $assignments = $workflowJob->workflowAssignments()->get();
            foreach($assignments as $assignment) {
                $pendingTask = $pendingJobRun->tasks()->create([
                    'user_id'                => user()->id,
                    'workflow_job_id'        => $workflowJob->id,
                    'workflow_assignment_id' => $assignment->id,
                    'status'                 => WorkflowTask::STATUS_PENDING,
                ]);

                Log::debug("\tDispatching Workflow Task $pendingTask->id");
                (new RunWorkflowTaskJob($pendingTask))->dispatch();
            }
        }
    }

    /**
     * A Workflow Task has finished. Set the Workflow Run as failed if the task is not complete.
     * If the Workflow Run has no more tasks to complete, then mark it as completed.
     * Otherwise, dispatch the next set of tasks for the Workflow Run if there are any remaining to be dispatched that
     * have their dependencies met.
     *
     * @param WorkflowTask $task
     * @return void
     * @throws Throwable
     */
    public static function taskFinished(WorkflowTask $task): void
    {
        $workflowRun = $task->workflowRun;

        if (!$task->isComplete()) {
            $workflowRun->failed_at = now();
            $workflowRun->save();
        }

        // If the workflow run has failed, stop processing
        if ($workflowRun->failed_at) {
            return;
        }

        // If we have completed all tasks in the workflow run, then mark the workflow run as completed
        if ($workflowRun->remainingTasks()->count() === 0) {
            $workflowRun->completed_at = now();
            $workflowRun->save();

            return;
        }


        WorkflowService::dispatchPendingWorkflowJobs($workflowRun);
    }
}
