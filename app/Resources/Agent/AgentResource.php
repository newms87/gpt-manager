<?php

namespace App\Resources\Agent;

use App\Models\Agent\Agent;
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
        return [
            'id'             => $this->id,
            'knowledge_name' => $this->knowledge?->name,
            'name'           => $this->name,
            'description'    => $this->description,
            'api'            => $this->api,
            'model'          => $this->model,
            'temperature'    => $this->temperature,
            'tools'          => $this->tools ?: [],
            'prompt'         => $this->prompt,
            'threads_count'  => $this->threads_count,
            'created_at'     => $this->created_at,
        ];
    }
}
