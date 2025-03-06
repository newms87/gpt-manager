<?php

namespace App\Repositories;

use App\Models\Task\TaskWorkflowRun;
use App\Models\Task\WorkflowStatesContract;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\TaskWorkflowRunnerService;
use DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class TaskWorkflowRunRepository extends ActionRepository
{
    public static string $model = TaskWorkflowRun::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createRun($data),
            'resume' => $this->resumeRun($model),
            'stop' => $this->stopRun($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createRun(array $data): TaskWorkflowRun
    {
        $taskWorkflow = team()->taskWorkflows()->find($data['task_workflow_id'] ?? null);

        if (!$taskWorkflow) {
            throw new ValidationError('Failed to run workflow: Task Workflow was not found');
        }

        $workflowInput = team()->workflowInputs()->find($data['workflow_input_id'] ?? null);

        return TaskWorkflowRunnerService::start($taskWorkflow, $workflowInput);
    }

    public function resumeRun(TaskWorkflowRun $taskWorkflowRun): TaskWorkflowRun
    {
        TaskWorkflowRunnerService::resume($taskWorkflowRun);

        return $taskWorkflowRun;
    }

    public function stopRun(TaskWorkflowRun $taskWorkflowRun): TaskWorkflowRun
    {
        TaskWorkflowRunnerService::stop($taskWorkflowRun);

        return $taskWorkflowRun;
    }

    /**
     * Get the count of workflow run statuses based on the filter
     */
    public function getRunStatuses($filter = []): array
    {
        $completedStatus = WorkflowStatesContract::STATUS_COMPLETED;
        $pendingStatus   = WorkflowStatesContract::STATUS_PENDING;
        $failedStatus    = WorkflowStatesContract::STATUS_FAILED;
        $runningStatus   = WorkflowStatesContract::STATUS_RUNNING;

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
}
