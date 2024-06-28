<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJob;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobResource extends ActionResource
{
    /**
     * @param WorkflowJob $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return static::make($model, [
                'id'            => $model->id,
                'name'          => $model->name,
                'description'   => $model->description,
                'runs_count'    => $model->workflowJobRuns()->count(),
                'use_input'     => $model->use_input,
                'workflow_tool' => $model->workflow_tool,
                'created_at'    => $model->created_at,
            ] + $attributes);
    }

    /**
     * @param WorkflowJob $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'workflow'     => WorkflowResource::data($model->workflow),
            'dependencies' => WorkflowJobDependencyResource::collection($model->dependencies),
            'assignments'  => WorkflowAssignmentResource::collection($model->workflowAssignments),
        ]);
    }
}
