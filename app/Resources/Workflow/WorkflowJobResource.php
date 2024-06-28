<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJob;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin WorkflowJob
 * @property WorkflowJob $resource
 */
class WorkflowJobResource extends ActionResource
{
    protected static string $type = 'WorkflowJob';

    public function data(): array
    {
        $data = [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'runs_count'    => $this->workflowJobRuns()->count(),
            'use_input'     => $this->use_input,
            'workflow_tool' => $this->workflow_tool,
            'created_at'    => $this->created_at,
        ];

        if ($this->relationLoaded('workflow')) {
            $data['workflow'] = WorkflowResource::make($this->workflow);
        }

        if ($this->relationLoaded('assignments')) {
            $data['assignments'] = WorkflowAssignmentResource::collection($this->workflowAssignments);
        }

        if ($this->relationLoaded('dependencies')) {
            $data['dependencies'] = WorkflowJobDependencyResource::collection($this->dependencies);
        }

        return $data;
    }
}
