<?php

namespace App\Repositories;

use App\Models\Agent\Agent;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;
}
