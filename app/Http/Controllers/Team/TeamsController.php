<?php

namespace App\Http\Controllers\Team;

use App\Repositories\TeamRepository;
use App\Resources\Auth\TeamResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TeamsController extends ActionController
{
    public static string  $repo     = TeamRepository::class;
    public static ?string $resource = TeamResource::class;
}
