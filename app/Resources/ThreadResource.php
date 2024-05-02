<?php

namespace App\Resources;

use App\Models\Agent\Thread;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Thread
 * @property Thread $resource
 */
class ThreadResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'summary'  => $this->summary,
            'messages' => MessageResource::collection($this->messages()->orderBy('id')->get()),
        ];
    }
}
