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
        $dependencies = $this->dependencies;

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'description'      => $this->description,
            'assignments'      => WorkflowAssignmentResource::collection($this->workflowAssignments),
            'runs_count'       => $this->workflowJobRuns()->count(),
            'dependencies'     => $dependencies->isNotEmpty() ? WorkflowJobDependencyResource::collection($dependencies) : null,
            'use_input_source' => $this->use_input_source,
            'workflow_tool'    => $this->workflow_tool,
            'created_at'       => $this->created_at,
        ];
    }
}
