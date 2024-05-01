<?php

namespace App\Resources;

use App\Models\Agent\Agent;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agent
 * @property Agent $resource
 */
class AgentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'knowledge_name' => $this->knowledge?->name,
            'name'           => $this->name,
            'description'    => $this->description,
            'model'          => $this->model,
            'temperature'    => $this->temperature,
            'functions'      => $this->functions,
            'prompt'         => $this->prompt,
            'created_at'     => $this->created_at,
        ];
    }
}
