<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Services\Workflow\WorkflowService;
use App\Services\Workflow\WorkflowTaskService;
use DB;
use Log;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowRunRepository extends ActionRepository
{
    public static string $model = WorkflowRun::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        match ($action) {
            'restart-workflow' => $this->restartWorkflowRun($model),
            'restart-job' => $this->restartWorkflowJobRun($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Mark a task as timed out, and update the statuses of the task, its parent job run, and the workflow run
     */
    public function taskTimedOut(WorkflowTask $task): void
    {
        Log::debug("Task $task timed out");

        $task->failed_at = now();
        $task->computeStatus()->save();

        $workflowJobRun = $task->workflowJobRun;

        // Mark the Workflow Job Run as failed
        if (!$workflowJobRun->failed_at) {
            Log::debug("$workflowJobRun failed due to task time out");
            $workflowJobRun->failed_at = now();
            $workflowJobRun->computeStatus()->save();
        }

        // Mark the Workflow Run as failed
        $workflowRun = $workflowJobRun->workflowRun()->withTrashed()->first();

        if (!$workflowRun->failed_at) {
            Log::debug("$workflowRun failed due to task time out");
            $workflowRun->failed_at = now();
            $workflowRun->computeStatus()->save();
        }
    }

    /**
     * Check for any tasks that have timed out, and update their statuses and the statuses of their parent job run and
     * workflow run
     */
    public function checkForTimeouts(WorkflowRun $workflowRun): WorkflowRun
    {
        foreach($workflowRun->workflowJobRuns as $workflowJobRun) {
            foreach($workflowJobRun->tasks as $task) {
                if ($task->isTimedOut()) {
                    $this->taskTimedOut($task);
                }
            }
        }

        return $workflowRun;
    }

    /**
     * Get the count of workflow run statuses based on the filter
     */
    public function getRunStatuses($filter = []): array
    {
        $completedStatus = WorkflowRun::STATUS_COMPLETED;
        $pendingStatus   = WorkflowRun::STATUS_PENDING;
        $failedStatus    = WorkflowRun::STATUS_FAILED;
        $runningStatus   = WorkflowRun::STATUS_RUNNING;

        return WorkflowRun::filter($filter)->select([
            DB::raw('COUNT(*) as total_count'),
            DB::raw("SUM(IF(status = '$completedStatus', 1, 0)) as completed_count"),
            DB::raw("SUM(IF(status = '$pendingStatus', 1, 0)) as pending_count"),
            DB::raw("SUM(IF(status = '$failedStatus', 1, 0)) as failed_count"),
            DB::raw("SUM(IF(status = '$runningStatus', 1, 0)) as running_count"),
        ])
            ->first()
            ->toArray();
    }

    public function restartWorkflowRun(WorkflowRun $workflowRun): WorkflowRun
    {
        $workflowRun->workflowJobRuns()->delete();
        $workflowRun->update([
            'started_at'   => null,
            'completed_at' => null,
            'failed_at'    => null,
        ]);

        WorkflowService::start($workflowRun);

        return $workflowRun;
    }

    public function restartWorkflowJobRun(WorkflowRun $workflowRun, array $data): WorkflowRun
    {
        $workflowJobRun = $workflowRun->workflowJobRuns()->find($data['workflow_job_run_id']);

        if (!$workflowJobRun) {
            throw new ValidationError('Workflow Job Run not found');
        }

        $workflowJobRun->tasks()->delete();
        $workflowJobRun->update([
            'started_at'   => null,
            'completed_at' => null,
            'failed_at'    => null,
        ]);
        $workflowRun->update([
            'completed_at' => null,
            'failed_at'    => null,
        ]);

        WorkflowService::dispatchPendingWorkflowJobs($workflowRun);
        WorkflowTaskService::dispatchPendingWorkflowTasks($workflowRun);

        return $workflowRun;
    }
}
