<?php

namespace App\Services\Workflow;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\LockHelper;
use Throwable;

class WorkflowService
{
    public function run(Workflow $workflow, WOrkflowInput $workflowInput): WorkflowRun
    {
        $workflowRun = $workflow->workflowRuns()->create([
            'workflow_input_id' => $workflowInput->id,
        ]);

        WorkflowService::start($workflowRun);

        return $workflowRun;
    }

    /**
     * Begin the Workflow by creating all the jobs for the Workflow Run
     *
     * @throws Throwable
     */
    public static function start(WorkflowRun $workflowRun): void
    {
        $workflowRun->started_at = now();
        $workflowRun->save();

        // Create all the jobs for the workflow run
        $workflowJobs = $workflowRun->workflow->sortedAgentWorkflowJobs()->get();
        foreach($workflowJobs as $workflowJob) {
            $workflowJobRun = $workflowRun->workflowJobRuns()->create([
                'workflow_job_id' => $workflowJob->id,
            ]);
            Log::debug("$workflowJob created $workflowJobRun");
        }

        static::dispatchPendingWorkflowJobs($workflowRun);
        WorkflowTaskService::dispatchPendingWorkflowTasks($workflowRun);
    }

    /**
     * Dispatch any pending workflow run jobs that have their dependencies met
     */
    public static function dispatchPendingWorkflowJobs(WorkflowRun $workflowRun): void
    {
        /** @var WorkflowJobRun[][]|Collection $workflowJobRunsByStatus */
        $workflowJobRunsByStatus = $workflowRun->workflowJobRuns()->get()->keyBy('workflow_job_id')->groupBy('status');

        if ($workflowJobRunsByStatus->has(WorkflowRun::STATUS_FAILED)) {
            Log::warning("$workflowRun has failed jobs, stopping dispatch");

            return;
        }

        /** @var WorkflowJobRun[]|Collection $completedJobRuns */
        $completedJobRuns = $workflowJobRunsByStatus->get(WorkflowRun::STATUS_COMPLETED);
        /** @var WorkflowJobRun[]|Collection $pendingJobRuns */
        $pendingJobRuns = $workflowJobRunsByStatus->get(WorkflowRun::STATUS_PENDING) ?? [];

        Log::debug("$workflowRun dispatching " . count($pendingJobRuns) . " pending jobs...");

        foreach($pendingJobRuns as $pendingJobRun) {
            $workflowJob = $pendingJobRun->workflowJob;
            $workflowJob->load('dependencies');
            $prerequisiteJobRuns = static::getPrerequisiteJobRuns($pendingJobRun, $completedJobRuns);

            // If the job has dependencies that have not yet completed, then skip this job
            if (count($prerequisiteJobRuns) < $workflowJob->dependencies->count()) {
                Log::debug("Waiting for dependencies: " . $workflowJob->dependencies->reduce(fn($str, $d) => $str . $d . ', ', ''));
                continue;
            }

            Log::debug("$workflowRun dispatching $pendingJobRun");

            $pendingJobRun->started_at = now();
            $pendingJobRun->save();

            $workflowJob->getWorkflowTool()->resolveAndAssignTasks($pendingJobRun, $prerequisiteJobRuns);
        }
    }

    /**
     * Get the prerequisite job runs for a given Workflow Job Run
     */
    public static function getPrerequisiteJobRuns(WorkflowJobRun $workflowJobRun, Collection $completedJobRuns = null): array
    {
        if (!$completedJobRuns) {
            return [];
        }

        $prerequisiteJobRuns = [];

        foreach($workflowJobRun->workflowJob->dependencies as $dependency) {
            /** @var WorkflowJobRun $completedJobRun */
            $completedJobRun = $completedJobRuns->where('workflow_job_id', $dependency->depends_on_workflow_job_id)->first();

            if ($completedJobRun) {
                $prerequisiteJobRuns[$dependency->depends_on_workflow_job_id] = $completedJobRun;
            }
        }

        return $prerequisiteJobRuns;
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

        if ($workflowJobRun->tasks()->whereNotNull('failed_at')->exists()) {
            $workflowJobRun->failed_at = now();
            $workflowJobRun->save();
            Log::debug("$workflowJobRun has failed");
        }

        // If the workflow run has failed, stop processing
        if ($workflowRun->failed_at) {
            Log::debug("$workflowRun has already failed, stopping dispatch");

            return;
        }

        // Make sure no race conditions accidentally complete a workflow when another one failed or double dispatch jobs
        LockHelper::acquire($workflowRun);

        // Save the artifact from the completed task
        $artifacts = $workflowJobRun->artifacts()->get();
        $workflowRun->artifacts()->syncWithoutDetaching($artifacts);
        Log::debug("$workflowRun attached {$artifacts->count()} artifacts: [" . $artifacts->pluck('id')->implode(',') . "]");

        // Mark the Workflow Job Run as completed
        $workflowJobRun->completed_at = now();
        $workflowJobRun->save();

        // If we have completed all Workflow Job Runs in the workflow run, then mark the workflow run as completed
        if ($workflowRun->remainingJobRuns()->doesntExist()) {
            if ($workflowRun->failedJobRuns()->exists()) {
                $workflowRun->failed_at = now();
                $workflowRun->save();
                Log::debug("$workflowRun has failed");
            } else {
                $workflowRun->completed_at = now();
                $workflowRun->save();
                Log::debug("$workflowRun has completed");
            }
        }

        if ($workflowRun->isRunning()) {
            Log::debug("$workflowRun dispatching next jobs..");
            // Dispatch the next set of Workflow Job Runs
            WorkflowService::dispatchPendingWorkflowJobs($workflowRun);
        }

        // Release the lock here as its possible while we are dispatching the tasks, another task has completed and would like to proceed
        LockHelper::release($workflowRun);

        // Check to see if the workflow run is still running. If so, dispatch the next set of tasks
        if ($workflowRun->refresh()->isRunning()) {
            WorkflowTaskService::dispatchPendingWorkflowTasks($workflowRun);
        }
    }
}
