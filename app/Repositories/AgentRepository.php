<?php

namespace App\Repositories;

use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use App\Models\Agent\Thread;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    /**
     * @param array $data
     * @return Agent
     * @throws ValidationError
     */
    public function createAgent(array $data): Model
    {
        // TODO: Implement this via Laravel validation
        if (Agent::where('name', $data['name'] ?? '')->exists()) {
            throw new ValidationError('An agent with this name already exists');
        }

        if (!empty($data['model'])) {
            $data['api'] = AgentRepository::getApiForModel($data['model']);
        } else {
            $data += [
                'api'   => OpenAiApi::$serviceName,
                'model' => 'gpt-4-turbo',
            ];
        }

        return Agent::create($data);
    }

    /**
     * @param Agent $agent
     * @param array $data
     * @return Agent
     */
    public function updateAgent(Agent $agent, array $data): Model
    {
        if (!empty($data['model'])) {
            $data['api'] = AgentRepository::getApiForModel($data['model']);
        }

        $agent->update($data);

        return $agent;
    }

    /**
     * @param string     $action
     * @param Agent      $model
     * @param array|null $data
     * @return Thread|bool|Model|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createAgent($data),
            'update' => $this->updateAgent($model, $data),
            'create-thread' => app(ThreadRepository::class)->create($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * @param array|null $filter
     * @return array
     */
    public function filterFieldOptions(?array $filter = []): array
    {
        $aiModels = collect(static::getAiModels())->sortKeys()->map(function ($aiModel) {
            $input  = $aiModel['details']['input'] * 1000;
            $output = $aiModel['details']['output'] * 1000;

            return [
                'label'   => $aiModel['api'] . ': ' . $aiModel['name'] . " (\$$input in + \$$output out / 1M tokens)",
                'value'   => $aiModel['name'],
                'details' => $aiModel['details'],
            ];
        })->values()->toArray();

        $aiTools = config('ai.tools');

        return [
            'aiModels' => $aiModels,
            'aiTools'  => $aiTools,
        ];
    }

    /**
     * Reverse lookup API from model
     *
     * @param string $model
     * @return string|null
     */
    public static function getApiForModel(string $model): ?string
    {
        foreach(static::getAiModels() as $aiModel) {
            if ($aiModel['name'] === $model) {
                return $aiModel['api'];
            }
        }

        return null;
    }

    /**
     * Get all available AI models
     *
     * @return array
     */
    public static function getAiModels(): array
    {
        $aiModels = [];
        $aiConfig = config('ai');

        foreach($aiConfig['apis'] as $apiName => $apiClass) {
            $models = $aiConfig['models'][$apiName] ?? [];
            foreach($models as $modelName => $model) {
                $aiModels[$apiName . ':' . $modelName] = [
                    'api'     => $apiName,
                    'name'    => $modelName,
                    'details' => $model,
                ];
            }
        }

        return $aiModels;
    }
}
