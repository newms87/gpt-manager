<?php

namespace App\Resources;

use App\Models\Agent\Thread;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin Thread
 * @property Thread $resource
 */
class ThreadResource extends ActionResource
{
    public function data(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'summary'  => $this->summary,
            'messages' => MessageResource::collection($this->messages()->orderBy('id')->get()),
        ];
    }
}
