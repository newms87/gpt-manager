<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TeamObjectRepository;
use App\Resources\Tortguard\TeamObjectResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TeamObjectsController extends ActionController
{
    public static string  $repo     = TeamObjectRepository::class;
    public static ?string $resource = TeamObjectResource::class;
}
