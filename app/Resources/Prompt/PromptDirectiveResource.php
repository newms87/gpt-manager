<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptDirective;
use Newms87\Danx\Resources\ActionResource;

class PromptDirectiveResource extends ActionResource
{
    public static function data(PromptDirective $promptDirective): array
    {
        return [
            'id'             => $promptDirective->id,
            'name'           => $promptDirective->name,
            'directive_text' => $promptDirective->directive_text,
            'agents_count'   => $promptDirective->agents_count,
            'created_at'     => $promptDirective->created_at,
            'updated_at'     => $promptDirective->updated_at,
        ];
    }
}
