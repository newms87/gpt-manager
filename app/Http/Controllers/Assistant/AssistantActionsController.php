<?php

namespace App\Http\Controllers\Assistant;

use App\Repositories\Assistant\UniversalAssistantRepository;
use App\Http\Resources\Assistant\AssistantActionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class AssistantActionsController extends ActionController
{
    public static ?string $repo     = UniversalAssistantRepository::class;
    public static ?string $resource = AssistantActionResource::class;
}