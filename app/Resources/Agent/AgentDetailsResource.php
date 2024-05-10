<?php

namespace App\Resources\Agent;

use App\Models\Agent\Agent;

/**
 * @mixin Agent
 * @property Agent $resource
 */
class AgentDetailsResource extends AgentResource
{
    public function data(): array
    {
        return [
                'threads' => ThreadResource::collection($this->threads()->orderByDesc('created_at')->get()),
            ] + parent::data();
    }
}
