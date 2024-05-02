<?php

namespace App\Resources;

use App\Models\Agent\Message;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 * @property Message $resource
 */
class MessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'role'      => $this->role,
            'title'     => $this->title,
            'summary'   => $this->summary,
            'content'   => $this->content,
            'timestamp' => $this->updated_at,
        ];
    }
}
