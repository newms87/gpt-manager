<?php

namespace App\Resources;

use App\Models\Agent\Message;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin Message
 * @property Message $resource
 */
class MessageResource extends ActionResource
{
    public static ?string $type = 'Message';

    public function data(): array
    {
        return [
            'id'        => $this->id,
            'role'      => $this->role,
            'title'     => $this->title,
            'summary'   => $this->summary,
            'content'   => $this->content,
            'data'      => $this->data,
            'timestamp' => $this->updated_at,
        ];
    }
}
