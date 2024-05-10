<?php

namespace App\Resources\Agent;

use App\Models\Agent\Message;
use Flytedan\DanxLaravel\Resources\ActionResource;
use Flytedan\DanxLaravel\Resources\StoredFileResource;

/**
 * @mixin Message
 * @property Message $resource
 */
class MessageResource extends ActionResource
{
    public static string $type = 'Message';

    public function data(): array
    {
        return [
            'id'         => $this->id,
            'role'       => $this->role,
            'title'      => $this->title,
            'summary'    => $this->summary,
            'content'    => $this->content,
            'files'      => StoredFileResource::collection($this->storedFiles),
            'data'       => $this->data,
            'timestamp'  => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
