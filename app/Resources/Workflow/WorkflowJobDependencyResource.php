<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobDependency;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin WorkflowJobDependency
 * @property WorkflowJobDependency $resource
 */
class WorkflowJobDependencyResource extends ActionResource
{
    protected static string $type = 'WorkflowJobDependency';

    public function data(): array
    {
        return [
            'id'                       => $this->id,
            'depends_on_id'            => $this->dependsOn->id,
            'depends_on_name'          => $this->dependsOn->name,
            'depends_on_workflow_tool' => $this->dependsOn->workflow_tool,
            'group_by'                 => $this->group_by,
        ];
    }
}
