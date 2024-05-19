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
            'id'         => $this->id,
            'depends_on' => $this->dependsOn->name,
            'group_by'   => $this->group_by,
        ];
    }
}
