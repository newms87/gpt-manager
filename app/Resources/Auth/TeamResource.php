<?php

namespace App\Resources\Auth;

use App\Models\Team\Team;
use Newms87\Danx\Resources\ActionResource;

class TeamResource extends ActionResource
{
    public static function data(Team $team): array
    {
        return [
            'id'        => $team->id,
            'name'      => $team->name,
            'namespace' => $team->namespace,
            'logo'      => $team->logo,
        ];
    }
}
