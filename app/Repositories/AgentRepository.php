<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Agent\Thread;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    /**
     * @param array $data
     * @return Agent
     */
    public function createAgent(array $data): Agent
    {
        $agent = Agent::make()->forceFill([
            'team_id' => team()->id,
        ]);

        $data += [
            'model'       => config('ai.default_model'),
            'temperature' => 0,
            'tools'       => [],
        ];

        return $this->updateAgent($agent, $data);
    }

    /**
     * @param Agent $agent
     * @param array $data
     * @return Agent
     */
    public function updateAgent(Agent $agent, array $data): Agent
    {
        $agent->fill($data);
        $agent->api = AgentRepository::getApiForModel($agent->model);

        $agent->validate()->save($data);

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
    public function fieldOptions(?array $filter = []): array
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

    public function calcTotalCost(Agent $agent, $inputTokens, $outputToken)
    {
        $inputTokens = $inputTokens ?? 0;
        $outputToken = $outputToken ?? 0;
        $modelCosts  = config('ai.models')[$agent->api][$agent->model] ?? null;

        if (!$modelCosts) {
            return null;
        }

        return ($modelCosts['input'] * $inputTokens / 1000) + ($modelCosts['output'] * $outputToken / 1000);
    }
}
