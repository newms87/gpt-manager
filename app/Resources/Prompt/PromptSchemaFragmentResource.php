<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptSchemaFragment;
use Newms87\Danx\Resources\ActionResource;

class PromptSchemaFragmentResource extends ActionResource
{
    public static function data(PromptSchemaFragment $promptSchemaFragment): array
    {
        return [
            'id'                => $promptSchemaFragment->id,
            'name'              => $promptSchemaFragment->name,
            'fragment_selector' => $promptSchemaFragment->fragment_selector,
            'created_at'        => $promptSchemaFragment->created_at,
            'updated_at'        => $promptSchemaFragment->updated_at,
        ];
    }
}
