<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptDirective;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class PromptDirectiveResource extends ActionResource
{
    /**
     * @param PromptDirective $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'             => $model->id,
            'name'           => $model->name,
            'directive_text' => $model->directive_text,
            'agents_count'   => $model->agents_count,
            'created_at'     => $model->created_at,
            'updated_at'     => $model->updated_at,
        ];
    }
}
