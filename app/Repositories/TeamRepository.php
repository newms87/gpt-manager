<?php

namespace App\Repositories;

use App\Models\Team\Team;
use Newms87\Danx\Repositories\ActionRepository;

class TeamRepository extends ActionRepository
{
    public static string $model = Team::class;
}
