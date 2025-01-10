<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptSchemaHistory;
use Newms87\Danx\Resources\ActionResource;

class PromptSchemaRevisionResource extends ActionResource
{
    public static function data(PromptSchemaHistory $promptSchemaHistory): array
    {
        return [
            'id'         => $promptSchemaHistory->id,
            'schema'     => $promptSchemaHistory->schema,
            'user_email' => $promptSchemaHistory->user?->email,
            'created_at' => $promptSchemaHistory->created_at,
        ];
    }
}
