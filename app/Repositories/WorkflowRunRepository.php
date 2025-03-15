<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Workflow\WorkflowRunnerService;
use DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowRunRepository extends ActionRepository
{
    public static string $model = WorkflowRun::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createRun($data),
            'resume' => $this->resumeRun($model),
            'stop' => $this->stopRun($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createRun(array $data): WorkflowRun
    {
        $workflowDefinition = team()->workflowDefinitions()->find($data['workflow_definition_id'] ?? null);

        if (!$workflowDefinition) {
            throw new ValidationError('Failed to run workflow: Workflow Definition was not found');
        }

        $workflowInput = team()->workflowInputs()->find($data['workflow_input_id'] ?? null);

        return WorkflowRunnerService::start($workflowDefinition, $workflowInput);
    }

    public function resumeRun(WorkflowRun $workflowRun): WorkflowRun
    {
        WorkflowRunnerService::resume($workflowRun);

        return $workflowRun;
    }

    public function stopRun(WorkflowRun $workflowRun): WorkflowRun
    {
        WorkflowRunnerService::stop($workflowRun);

        return $workflowRun;
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
