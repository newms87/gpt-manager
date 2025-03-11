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
            'directive' => $promptDirective->directive ? PromptDirectiveResource::make($promptDirective->directive) : null,
            'position'  => $promptDirective->position,
            'section'   => $promptDirective->section,
        ];
    }
}
