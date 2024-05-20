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
            Log::debug("Creating run for $workflowJob");
            $workflowRun->workflowJobRuns()->create([
                'workflow_job_id' => $workflowJob->id,
            ]);
        }

        static::dispatchPendingWorkflowJobs($workflowRun);
        static::dispatchPendingWorkflowTasks($workflowRun);
    }

    /**
     * @param WorkflowRun $workflowRun
     * @return void
     * @throws Throwable
     */
    public static function dispatchPendingWorkflowTasks(WorkflowRun $workflowRun): void
    {
        foreach($workflowRun->runningJobRuns()->get() as $pendingJobRun) {
            foreach($pendingJobRun->pendingTasks()->get() as $pendingTask) {
                Log::debug("$pendingJobRun dispatching $pendingTask");
                $job                          = (new RunWorkflowTaskJob($pendingTask))->dispatch();
                $pendingTask->job_dispatch_id = $job?->getJobDispatch()?->id;
                $pendingTask->save();
            }
        }
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
        /** @var WorkflowJobRun[]|Collection $pendingJobRuns */
        $pendingJobRuns = $workflowJobRunsByStatus->get(WorkflowRun::STATUS_PENDING) ?? [];

        foreach($pendingJobRuns as $pendingJobRun) {
            $workflowJob   = $pendingJobRun->workflowJob;
            $dependencies  = $workflowJob->dependencies()->get();
            $dependsOnJobs = [];

            // Resolve the dependencies from the completed job runs.
            // Associate the completed job run with the dependency configuration, so we can group the output data of the artifacts to pass to the next job.
            if ($completedJobRuns) {
                foreach($dependencies as $dependency) {
                    $completedJobRun = $completedJobRuns->where('workflow_job_id', $dependency->depends_on_workflow_job_id)->first();

                    if ($completedJobRun) {
                        $dependsOnJobs[$dependency->depends_on_workflow_job_id] = [
                            'id'      => $dependency->depends_on_workflow_job_id,
                            'jobRun'  => $completedJobRun,
                            'groupBy' => $dependency->group_by,
                        ];
                    }
                }
            }

            // If the job has dependencies that have not yet completed, then skip this job
            if (count($dependsOnJobs) < $dependencies->count()) {
                Log::debug("Job $workflowJob->name has dependencies that have not yet completed");
                continue;
            }

            Log::debug("$workflowRun dispatching $pendingJobRun");

            $pendingJobRun->started_at = now();
            $pendingJobRun->save();

            // Resolve the unique task groups to create a task for each group.
            // If there are no task groups, just setup a default task group
            $taskGroups  = WorkflowService::getTaskGroups($dependsOnJobs) ?: [''];
            $assignments = $workflowJob->workflowAssignments()->get();
            foreach($assignments as $assignment) {
                foreach($taskGroups as $taskGroup) {
                    $pendingJobRun->tasks()->create([
                        'user_id'                => user()->id,
                        'workflow_job_id'        => $workflowJob->id,
                        'workflow_assignment_id' => $assignment->id,
                        'group'                  => $taskGroup,
                        'status'                 => WorkflowTask::STATUS_PENDING,
                    ]);
                }
            }
        }
    }

    /**
     * Resolves all the groupings of data from the artifacts produced by the WorkflowJobRun dependencies.
     * NOTE: if no group by is set on the WorkflowJobDependency, then it is skipped.
     * returning an empty set of task groups means the entire artifact should be used for all tasks, and only 1 task
     * per assignment should be created.
     * @param array $dependsOnJobs
     * @return array
     */
    public static function getTaskGroups(array $dependsOnJobs): array
    {
        $taskGroups = [];

        foreach($dependsOnJobs as $dependsOnJob) {
            // If the dependency has no grouping set, then skip it
            if (empty($dependsOnJob['groupBy'])) {
                continue;
            }

            /** @var WorkflowJobRun $dependsOnJobRun */
            $dependsOnJobRun = $dependsOnJob['jobRun'];
            $artifacts       = $dependsOnJobRun->artifacts()->get();
            foreach($artifacts as $artifact) {
                $artifactGroups = $artifact->groupContentBy($dependsOnJob['groupBy']);
                if ($artifactGroups) {
                    $groups     = array_keys($artifactGroups);
                    $taskGroups = array_merge($taskGroups, $groups);
                }
            }
        }

        return $taskGroups;
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
            $artifact = $task->artifact()->first();
            $workflowJobRun->artifacts()->save($artifact);
            Log::debug("$workflowJobRun attached $artifact");

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
        Log::debug("$workflowJobRun finished running");
        $workflowRun = $workflowJobRun->workflowRun;

        // If the workflow run has failed, stop processing
        if ($workflowRun->failed_at) {
            Log::debug("$workflowRun has already failed, stopping dispatch");

            return;
        }

        // Make sure no race conditions accidentally complete a workflow when another one failed or double dispatch jobs
        LockHelper::acquire($workflowRun);

        if ($workflowJobRun->isComplete()) {
            // Save the artifact from the completed task
            $artifacts = $workflowJobRun->artifacts()->get();
            $workflowRun->artifacts()->saveMany($artifacts);
            Log::debug("$workflowRun attached {$artifacts->count()} artifacts");

            // If we have completed all Workflow Job Runs in the workflow run, then mark the workflow run as completed
            if ($workflowRun->remainingJobRuns()->doesntExist()) {
                $workflowRun->completed_at = now();
                $workflowRun->save();
                Log::debug("$workflowRun has completed");
            }
        } else {
            // If the Workflow Job Run failed, then mark the Workflow Run as failed
            $workflowRun->failed_at = now();
            $workflowRun->save();
            Log::debug("$workflowRun has failed");
        }

        if ($workflowRun->isRunning()) {
            Log::debug("$workflowRun dispatching next jobs..");
            // Dispatch the next set of Workflow Job Runs
            WorkflowService::dispatchPendingWorkflowJobs($workflowRun);

            // Release the lock here as its possible while we are dispatching the tasks, another task has completed and would like to proceed
            LockHelper::release($workflowRun);
            WorkflowService::dispatchPendingWorkflowTasks($workflowRun);
        } else {
            Log::debug("$workflowRun done");
            LockHelper::release($workflowRun);
        }
    }
}
