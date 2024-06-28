<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowAssignment;
use App\Resources\Agent\AgentResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowAssignmentResource extends ActionResource
{
    /**
     * @param WorkflowAssignment $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return static::make($model, [
                'id'           => $model->id,
                'is_required'  => $model->is_required,
                'max_attempts' => $model->max_attempts,
                'created_at'   => $model->created_at,
            ] + $attributes);
    }

    /**
     * @param WorkflowAssignment $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'agent'       => AgentResource::data($model->agent),
            'workflowJob' => WorkflowJobResource::data($model->workflowJob),
        ]);
    }
}
