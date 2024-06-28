<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowAssignment;
use App\Resources\Agent\AgentResource;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin WorkflowAssignment
 * @property WorkflowAssignment $resource
 */
class WorkflowAssignmentResource extends ActionResource
{
    protected static string $type = 'WorkflowAssignment';

    public function data(): array
    {
        $data = [
            'id'           => $this->id,
            'is_required'  => $this->is_required,
            'max_attempts' => $this->max_attempts,
            'created_at'   => $this->created_at,
        ];

        if ($this->relationLoaded('agent')) {
            $data['agent'] = AgentResource::make($this->agent);
        }

        if ($this->relationLoaded('workflowJob')) {
            $data['workflowJob'] = WorkflowJobResource::make($this->workflowJob);
        }

        return $data;
    }
}
