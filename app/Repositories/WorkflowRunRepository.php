<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use App\Services\Workflow\WorkflowTaskService;
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
