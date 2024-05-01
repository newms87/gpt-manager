<?php

namespace App\Repositories;

use App\Models\Agent\Agent;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function filterFieldOptions(?array $filter = []): array
    {
        return [
            'models' => Agent::getAiModelNames(),
        ];
    }
}
