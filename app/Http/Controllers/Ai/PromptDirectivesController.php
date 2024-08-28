<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\PromptDirectiveRepository;
use App\Resources\Prompt\PromptDirectiveResource;
use Newms87\Danx\Http\Controllers\ActionController;

class PromptDirectivesController extends ActionController
{
    public static string  $repo     = PromptDirectiveRepository::class;
    public static ?string $resource = PromptDirectiveResource::class;
}
