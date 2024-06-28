<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobRun;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobRunResource extends ActionResource
{
    /**
     * @param WorkflowJobRun $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return static::make($model, [
                'id'           => $model->id,
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
            ] + $attributes);
    }

    /**
     * @param WorkflowJobRun $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'workflowJob' => WorkflowJobResource::data($model->workflowJob),
            'tasks'       => WorkflowTaskResource::collection($model->tasks),
        ]);
    }
}
