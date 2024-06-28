<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowRunResource extends ActionResource
{
    /**
     * @param WorkflowRun $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return static::make($model, [
                'id'            => $model->id,
                'workflow_id'   => $model->workflow_id,
                'workflow_name' => $model->workflow->name,
                'status'        => $model->status,
                'started_at'    => $model->started_at,
                'completed_at'  => $model->completed_at,
                'failed_at'     => $model->failed_at,
                'created_at'    => $model->created_at,
                'usage'         => [
                    'input_tokens'  => $model->getTotalInputTokens(),
                    'output_tokens' => $model->getTotalOutputTokens(),
                    'cost'          => $model->getTotalCost(),
                ],
            ] + $attributes);
    }

    /**
     * @param WorkflowRun $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'artifacts'       => ArtifactResource::collection($model->artifacts),
            'workflowInput'   => WorkflowInputResource::data($model->workflowInput),
            'workflowJobRuns' => WorkflowJobRunResource::collection($model->sortedWorkflowJobRuns()->with(['workflowJob'])->get()),
        ]);
    }
}
