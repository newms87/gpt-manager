<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobDependency;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobDependencyResource extends ActionResource
{
    public static function data(WorkflowJobDependency $workflowJobDependency): array
    {
        return [
            'id'                       => $workflowJobDependency->id,
            'depends_on_id'            => $workflowJobDependency->dependsOn->id,
            'depends_on_name'          => $workflowJobDependency->dependsOn->name,
            'depends_on_workflow_tool' => $workflowJobDependency->dependsOn->workflow_tool,
            'depends_on_fields'        => $workflowJobDependency->dependsOn->getResponseFields(),
            'force_schema'             => $workflowJobDependency->force_schema,
            'include_fields'           => $workflowJobDependency->include_fields,
            'group_by'                 => $workflowJobDependency->group_by,
            'order_by'                 => $workflowJobDependency->order_by,
        ];
    }
}
