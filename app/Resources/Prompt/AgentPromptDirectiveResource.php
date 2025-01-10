<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\AgentPromptDirective;
use Newms87\Danx\Resources\ActionResource;

class AgentPromptDirectiveResource extends ActionResource
{
    public static function data(AgentPromptDirective $promptDirective): array
    {
        return [
            'id'        => $promptDirective->id,
            'directive' => PromptDirectiveResource::make($promptDirective->directive),
            'position'  => $promptDirective->position,
            'section'   => $promptDirective->section,
        ];
    }
}
