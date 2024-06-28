<?php

namespace App\Resources\Agent;

use App\Models\Agent\Agent;
use App\Resources\Workflow\WorkflowAssignmentResource;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin Agent
 * @property Agent $resource
 */
class AgentResource extends ActionResource
{
    protected static string $type = 'Agent';

    public function data(): array
    {
        $threads     = $this->resolveFieldRelation('threads');
        $assignments = $this->resolveFieldRelation('assignments', null, fn() => $this->assignments()->with('workflowJob.workflow')->get());

        return [
            'id'                => $this->id,
            'knowledge_name'    => $this->knowledge?->name,
            'name'              => $this->name,
            'description'       => $this->description,
            'api'               => $this->api,
            'model'             => $this->model,
            'temperature'       => $this->temperature,
            'tools'             => $this->tools ?: [],
            'prompt'            => $this->prompt,
            'threads_count'     => $this->threads_count,
            'assignments_count' => $this->assignments_count,
            'created_at'        => $this->created_at,
            'threads'           => ThreadResource::collection($threads),
            'assignments'       => WorkflowAssignmentResource::collection($assignments),
        ];
    }
}
