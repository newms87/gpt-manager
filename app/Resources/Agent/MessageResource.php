<?php

namespace App\Resources\Agent;

use App\Models\Agent\AgentThreadMessage;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\Audit\ApiLogResource;
use Newms87\Danx\Resources\StoredFileResource;

class MessageResource extends ActionResource
{
    public static function data(AgentThreadMessage $message): array
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
            'api_log_id' => $message->api_log_id,
            'files'      => fn($fields) => StoredFileResource::collection($message->storedFiles->load('transcodes'), $fields),
            'apiLog'     => fn($fields) => $message->apiLog ? ApiLogResource::make($message->apiLog, $fields) : null,
        ];
    }
}
