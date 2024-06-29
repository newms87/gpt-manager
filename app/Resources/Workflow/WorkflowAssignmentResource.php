<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowAssignment;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowAssignmentResource extends ActionResource
{
    /**
     * @param WorkflowAssignment $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'           => $model->id,
            'is_required'  => $model->is_required,
            'max_attempts' => $model->max_attempts,
            'created_at'   => $model->created_at,
        ];
    }
}
