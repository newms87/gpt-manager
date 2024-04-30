<?php

namespace App\Repositories;

use App\Models\Agent\Agent;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function filterFieldOptions(?array $filter = []): array
    {
        $models = $this->query()->select(['model'])->distinct()->pluck('model');

        return [
            'models' => $models,
        ];
    }
}
