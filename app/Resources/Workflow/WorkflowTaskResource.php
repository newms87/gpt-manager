<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowTask;
use App\Resources\Agent\ThreadResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowTaskResource extends ActionResource
{
    /**
     * @param WorkflowTask $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'           => $model->id,
            'job_name'     => $model->workflowJob?->name,
            'group'        => $model->group,
            'agent_name'   => $model->workflowAssignment?->agent->name,
            'model'        => $model->thread?->runs()->first()?->agent_model ?? $model->thread?->agent?->model,
            'status'       => $model->status,
            'started_at'   => $model->started_at,
            'completed_at' => $model->completed_at,
            'failed_at'    => $model->failed_at,
            'created_at'   => $model->created_at,
            'usage'        => [
                'input_tokens'  => $model->getTotalInputTokens(),
                'output_tokens' => $model->getTotalOutputTokens(),
                'cost'          => $model->getTotalCost(),
            ],
        ];
    }

    /**
     * @param WorkflowTask $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'audit_request_id' => $model->jobDispatch?->runningAuditRequest?->id,
            'logs'             => $model->jobDispatch?->runningAuditRequest?->logs,
            'thread'           => ThreadResource::make($model->thread),
        ]);
    }
}
