<?php

namespace App\Resources\Agent;

use App\Models\Agent\Message;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class MessageResource extends ActionResource
{
    public static function data(Message $message): array
    {
        return [
            'id'         => $message->id,
            'role'       => $message->role,
            'title'      => $message->title,
            'summary'    => $message->summary,
            'content'    => $message->content,
            'data'       => $message->data,
            'timestamp'  => $message->updated_at,
            'created_at' => $message->created_at,
            'files'      => fn($fields) => StoredFileResource::collection($message->storedFiles->load('transcodes'), $fields),
        ];
    }
}
