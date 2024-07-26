<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobDependency;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobDependencyResource extends ActionResource
{
    /**
     * @param WorkflowJobDependency $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                       => $model->id,
            'depends_on_id'            => $model->dependsOn->id,
            'depends_on_name'          => $model->dependsOn->name,
            'depends_on_workflow_tool' => $model->dependsOn->workflow_tool,
            'depends_on_response'      => $model->dependsOn->getResponsePreview(),
            'include_fields'           => $model->include_fields,
            'group_by'                 => $model->group_by,
        ];
    }
}
