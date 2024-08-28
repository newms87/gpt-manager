<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\AgentPromptDirective;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class AgentPromptDirectiveResource extends ActionResource
{
    /**
     * @param AgentPromptDirective $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'        => $model->id,
            'directive' => PromptDirectiveResource::make($model->directive),
            'position'  => $model->position,
            'section'   => $model->section,
        ];
    }
}
