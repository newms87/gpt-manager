<?php

namespace App\Resources;

use App\Models\Agent\Agent;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin Agent
 * @property Agent $resource
 */
class AgentResource extends ActionResource
{
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
            'functions'      => $this->functions,
            'prompt'         => $this->prompt,
            'threads'        => ThreadResource::collection($this->threads()->orderByDesc('created_at')->get()),
            'created_at'     => $this->created_at,
        ];
    }
}
