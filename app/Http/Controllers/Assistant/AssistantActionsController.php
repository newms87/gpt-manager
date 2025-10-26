<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Resources\Assistant\AssistantActionResource;
use App\Repositories\Assistant\UniversalAssistantRepository;
use Newms87\Danx\Http\Controllers\ActionController;

class AssistantActionsController extends ActionController
{
    public static ?string $repo     = UniversalAssistantRepository::class;

    public static ?string $resource = AssistantActionResource::class;
}
