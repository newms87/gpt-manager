<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id)->whereNull('resource_package_import_id');
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(threads_count) as threads_count"),
        ]);
    }

    public function fieldOptions(?array $filter = []): array
    {
        $aiModels = [];
        foreach(config('ai.models') as $api => $apiModels) {
            foreach($apiModels as $modelName => $modelDetails) {
                $aiModels[] = [
                    'name'    => $modelName,
                    'api'     => $api,
                    'details' => $modelDetails,
                ];
            }
        }

        return [
            'aiModels' => $aiModels,
        ];
    }

    /**
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createAgent($data),
            'update' => $this->updateAgent($model, $data),
            'copy' => $this->copyAgent($model),
            'create-thread' => app(ThreadRepository::class)->create($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create an agent applying business rules
     */
    public function createAgent(array $data): Agent
    {
        $agent = Agent::make()->forceFill([
            'team_id' => team()->id,
        ]);

        $data += [
            'model'       => config('ai.default_model'),
            'retry_count' => 2,
            'api_options' => array_merge($data['api_options'] ?? [], [
                'temperature' => 0.7
            ]),
        ];

        $agent->fill($data);
        $agent->name = ModelHelper::getNextModelName($agent);
        $agent->api  = static::getApiForModel($agent->model);
        $agent->validate()->save();

        return $agent;
    }

    /**
     * Update an agent applying business rules
     */
    public function updateAgent(Agent $agent, array $data): Agent
    {
        $agent->fill($data)->validate()->save($data);

        return $agent;
    }

    /**
     * Copy an agent
     */
    public function copyAgent(Agent $agent)
    {
        $newAgent       = $agent->replicate(['threads_count']);
        $newAgent->name = ModelHelper::getNextModelName($agent);
        $newAgent->save();

        return $newAgent;
    }

    /**
     * Reverse lookup API from model
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

    /**
     * Calculate the total cost of using the agent based in input and output tokens accumulated over all thread runs
     */
    public function calcTotalCost(Agent $agent, $inputTokens, $outputToken): ?float
    {
        $inputTokens = $inputTokens ?? 0;
        $outputToken = $outputToken ?? 0;
        $modelCosts  = config('ai.models')[$agent->api][$agent->model] ?? null;

        if (!$modelCosts) {
            return null;
        }

        return ($modelCosts['input'] * $inputTokens) + ($modelCosts['output'] * $outputToken);
    }
}
