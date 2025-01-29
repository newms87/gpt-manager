<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\PromptSchemaFragmentRepository;
use App\Resources\Prompt\PromptSchemaFragmentResource;
use Newms87\Danx\Http\Controllers\ActionController;

class PromptSchemaFragmentsController extends ActionController
{
    public static string  $repo     = PromptSchemaFragmentRepository::class;
    public static ?string $resource = PromptSchemaFragmentResource::class;
}
