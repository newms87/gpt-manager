<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowTask;
use App\Resources\Agent\ThreadResource;
use Newms87\Danx\Resources\ActionResource;

class WorkflowTaskResource extends ActionResource
{
    public static function data(WorkflowTask $workflowTask): array
    {
        return [
            'id'           => $workflowTask->id,
            'job_name'     => $workflowTask->workflowJob?->name,
            'group'        => $workflowTask->group,
            'agent_id'     => $workflowTask->workflowAssignment?->agent->id,
            'agent_name'   => $workflowTask->workflowAssignment?->agent->name,
            'model'        => $workflowTask->thread?->runs()->first()?->agent_model ?? $workflowTask->thread?->agent?->model,
            'status'       => $workflowTask->status,
            'started_at'   => $workflowTask->started_at,
            'completed_at' => $workflowTask->completed_at,
            'failed_at'    => $workflowTask->failed_at,
            'created_at'   => $workflowTask->created_at,
            'usage'        => [
                'input_tokens'  => $workflowTask->getTotalInputTokens(),
                'output_tokens' => $workflowTask->getTotalOutputTokens(),
                'total_cost'    => $workflowTask->getTotalCost(),
            ],

            'audit_request_id' => fn() => $workflowTask->jobDispatch?->runningAuditRequest?->id,
            'logs'             => fn() => $workflowTask->jobDispatch?->runningAuditRequest?->logs,
            'thread'           => fn($fields) => ThreadResource::make($workflowTask->thread, $fields),
        ];
    }
}
