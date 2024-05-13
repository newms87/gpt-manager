<?php

namespace App\Services\Workflow;

use App\Jobs\RunWorkflowTaskJob;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowService
{
    /**
     * Begin the Workflow by creating all the tasks for jobs that do not have any dependencies
     *
     * @throws Throwable
     */
    public static function start(WorkflowRun $workflowRun): void
    {
        $workflowRun->started_at = now();
        $workflowRun->save();

        $workflowJobs = $workflowRun->workflow->workflowJobs()->get();
        foreach($workflowJobs as $workflowJob) {
            Log::debug("Creating tasks for job $workflowJob->id");

            $assignments = $workflowJob->workflowAssignments()->get();
            foreach($assignments as $assignment) {
                $assignment->workflowTasks()->create([
                    'user_id'         => user()->id,
                    'workflow_run_id' => $workflowRun->id,
                    'workflow_job_id' => $workflowJob->id,
                    'status'          => WorkflowTask::STATUS_PENDING,
                ]);
            }
        }

        static::dispatchTasksWithDependenciesMet($workflowRun);
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


        WorkflowService::dispatchTasksWithDependenciesMet($workflowRun);
    }

    /**
     * Dispatch the next set of tasks for the Workflow Run if there are any remaining to be dispatched that have their
     * dependencies met
     *
     * @param WorkflowRun $workflowRun
     * @return void
     * @throws Throwable
     */
    public static function dispatchTasksWithDependenciesMet(WorkflowRun $workflowRun): void
    {
        $workflow = $workflowRun->workflow;

        // Get a mapped list of jobs and whether they have been completed
        $completedJobs = [];
        foreach($workflow->workflowJobs as $workflowJob) {
            $completedJobs[$workflowJob->id] = $workflowJob->remainingTasks()->count() === 0;
        }

        // Dispatch any tasks that have their dependencies met
        foreach($workflowRun->pendingTasks as $pendingTask) {
            $dependsOnJobs = $pendingTask->workflowJob->depends_on ?: [];

            foreach($dependsOnJobs as $workflowJobId) {
                // If the job has not been completed, then we cannot dispatch this task
                if (empty($completedJobs[$workflowJobId])) {
                    continue 2;
                }
            }

            Log::debug("Dispatching task $pendingTask->id");
            (new RunWorkflowTaskJob($pendingTask))->dispatch();
        }
    }
}
