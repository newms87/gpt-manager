<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptSchemaHistory;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class PromptSchemaRevisionResource extends ActionResource
{
    /**
     * @param PromptSchemaHistory $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'         => $model->id,
            'schema'     => $model->schema,
            'user_email' => $model->user?->email,
            'created_at' => $model->created_at,
        ];
    }
}
