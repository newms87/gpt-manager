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
            DB::raw('SUM(threads_count) as threads_count'),
        ]);
    }

    public function fieldOptions(?array $filter = []): array
    {
        $aiModels = [];
        foreach (config('ai.models') as $modelName => $modelDetails) {
            $aiModels[] = [
                'name'    => $modelName,
                'api'     => $modelDetails['api'] ?? null,
                'details' => $modelDetails,
            ];
        }

        return [
            'aiModels' => $aiModels,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create'        => $this->createAgent($data),
            'update'        => $this->updateAgent($model, $data),
            'copy'          => $this->copyAgent($model),
            'create-thread' => app(ThreadRepository::class)->create($model),
            default         => parent::applyAction($action, $model, $data)
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
        ];

        $agent->fill($data);
        $agent->name = ModelHelper::getNextModelName($agent);
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
}
