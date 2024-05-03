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
    protected static ?string $type = 'Agent';

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
            'thread_count'   => $this->threads()->count(),
            'created_at'     => $this->created_at,
        ];
    }
}
