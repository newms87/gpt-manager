<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJob;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin WorkflowJob
 * @property WorkflowJob $resource
 */
class WorkflowJobResource extends ActionResource
{
    protected static string $type = 'WorkflowJob';

    public function data(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'assignments' => WorkflowAssignmentResource::collection($this->workflowAssignments),
            'runs_count'  => $this->workflowTasks()->count(),
            'created_at'  => $this->created_at,
        ];
    }
}
