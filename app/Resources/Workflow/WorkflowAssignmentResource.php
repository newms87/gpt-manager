<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowAssignment;
use Newms87\Danx\Resources\ActionResource;

class WorkflowAssignmentResource extends ActionResource
{
    public static function data(WorkflowAssignment $workflowAssignment): array
    {
        return [
            'id'           => $workflowAssignment->id,
            'is_required'  => $workflowAssignment->is_required,
            'max_attempts' => $workflowAssignment->max_attempts,
            'created_at'   => $workflowAssignment->created_at,

            'workflowJob' => fn($fields) => WorkflowJobResource::make($workflowAssignment->workflowJob, $fields),
        ];
    }
}
