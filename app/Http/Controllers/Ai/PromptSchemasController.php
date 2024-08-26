<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\PromptSchemaRepository;
use App\Resources\Prompt\PromptSchemaResource;
use Newms87\Danx\Http\Controllers\ActionController;

class PromptSchemasController extends ActionController
{
    public static string  $repo     = PromptSchemaRepository::class;
    public static ?string $resource = PromptSchemaResource::class;
}
