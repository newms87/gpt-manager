<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Resources\ActionResource;

class WorkflowRunResource extends ActionResource
{
    public static function data(WorkflowRun $workflowRun): array
    {
        return [
            'name'                   => $workflowRun->name,
            'status'                 => $workflowRun->status,
            'workflow_definition_id' => $workflowRun->workflow_definition_id,
            'active_workers_count'   => $workflowRun->active_workers_count,
            'error_count'            => $workflowRun->error_count,
            'progress_percent'       => $workflowRun->calculateProgress(),
            'total_nodes'            => $workflowRun->workflowDefinition->workflowNodes()->count(),
            'completed_tasks'        => $workflowRun->taskRuns()
                ->whereIn('status', [
                    WorkflowStatesContract::STATUS_COMPLETED,
                    WorkflowStatesContract::STATUS_FAILED,
                    WorkflowStatesContract::STATUS_SKIPPED,
                ])
                ->count(),
            'started_at'             => $workflowRun->started_at,
            'stopped_at'             => $workflowRun->stopped_at,
            'failed_at'              => $workflowRun->failed_at,
            'completed_at'           => $workflowRun->completed_at,
            'created_at'             => $workflowRun->created_at,
            'updated_at'             => $workflowRun->updated_at,
            'taskRuns'               => fn($fields) => TaskRunResource::collection($workflowRun->taskRuns, $fields),
        ];
    }
}
