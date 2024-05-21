<?php

namespace App\Services\Workflow;

use App\Jobs\RunWorkflowTaskJob;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\WorkflowTools\TranscodeInputSourceWorkflowTool;
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

        // Setup the Input Source Transcoding Job
        static::prependTranscodeInputSourceJobInWorkflow($workflowRun->workflow);

        // Create all the jobs for the workflow run
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

    public static function prependTranscodeInputSourceJobInWorkflow(Workflow $workflow): void
    {
        $name = 'Prepare Input Source';

        // If the job already exists, then there is nothing to do
        if ($workflow->workflowJobs()->where('name', $name)->exists()) {
            Log::debug("$workflow already has $name job");

            return;
        }

        $workflowJobs            = $workflow->workflowJobs()->get();
        $transcodeInputSourceJob = $workflow->workflowJobs()->create([
            'name'             => $name,
            'use_input_source' => true,
            'workflow_tool'    => TranscodeInputSourceWorkflowTool::class,
        ]);

        // Only make the Input source a dependency for the jobs that require it
        foreach($workflowJobs as $workflowJob) {
            if ($workflowJob->use_input_source) {
                $workflowJob->dependencies()->create([
                    'depends_on_workflow_job_id' => $transcodeInputSourceJob->id,
                ]);
            }
        }

        Log::debug("$workflow prepended $transcodeInputSourceJob");
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

            $workflowJob->getWorkflowTool()->assignTasks($pendingJobRun, $dependsOnJobs);
        }
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
