<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function createAgent(array $data): Model
    {
        // TODO: Implement this via Laravel validation
        if (Agent::where('name', $data['name'] ?? '')->exists()) {
            throw new ValidationError('An agent with this name already exists');
        }

        return Agent::create($data);
    }

    public function applyAction(string $action, ?Model $model, array $data)
    {
        return match ($action) {
            'create' => $this->createAgent($data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function filterFieldOptions(?array $filter = []): array
    {
        return [
            'models' => Agent::getAiModelNames(),
        ];
    }
}
