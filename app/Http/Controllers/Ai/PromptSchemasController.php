<?php

namespace App\Http\Controllers\Ai;

use App\Models\Prompt\PromptSchema;
use App\Repositories\PromptSchemaRepository;
use App\Resources\Prompt\PromptSchemaResource;
use App\Resources\Prompt\PromptSchemaRevisionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class PromptSchemasController extends ActionController
{
    public static string  $repo     = PromptSchemaRepository::class;
    public static ?string $resource = PromptSchemaResource::class;

    public function history(PromptSchema $promptSchema)
    {
        return PromptSchemaRevisionResource::collection($promptSchema->promptSchemaRevisions()->orderByDesc('id')->get());
    }
}
