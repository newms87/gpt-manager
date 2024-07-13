<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Repositories\ActionRepository;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(threads_count) as threads_count"),
            DB::raw("SUM(assignments_count) as assignments_count"),
        ]);
    }

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
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createAgent($data),
            'update' => $this->updateAgent($model, $data),
            'copy' => $this->copyAgent($model),
            'create-thread' => app(ThreadRepository::class)->create($model),
            'generate-sample' => $this->generateSample($model),
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
            'temperature' => 0,
            'tools'       => [],
        ];

        return $this->updateAgent($agent, $data);
    }

    /**
     * Update an agent applying business rules
     */
    public function updateAgent(Agent $agent, array $data): Agent
    {
        $agent->fill($data);
        $agent->api = AgentRepository::getApiForModel($agent->model);

        if ($agent->isDirty('response_schema')) {
            $agent->response_sample = null;
        }

        $agent->validate()->save($data);

        return $agent;
    }

    /**
     * Copy an agent
     */
    public function copyAgent(Agent $agent)
    {
        $newAgent = $agent->replicate(['threads_count', 'assignments_count']);
        $count    = 1;
        do {
            $newAgent->name = $agent->name . " ($count)";
            $count++;
        } while(Agent::where('name', $newAgent->name)->exists());

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

        return ($modelCosts['input'] * $inputTokens / 1000) + ($modelCosts['output'] * $outputToken / 1000);
    }

    /**
     * Generate a sample response based on the prompt and response schema.
     *
     * This is useful for feedback for the user to validate the response schema and also used to identify available
     * fields for grouping in Agent Assignments.
     */
    public function generateSample(Agent $agent)
    {
        $threadRepo = app(ThreadRepository::class);
        $thread     = $threadRepo->create($agent, 'Response Sample');
        $threadRepo->addMessageToThread($thread, 'Create a response with example data. Provide a robust sample response so all fields have been resolved with all permutations of fields. The goal is to create a response with an example that shows all possible fields for a response (even if fields are mutually exclusive, include all fields). Respond with JSON only! NO OTHER TEXT.');

        $threadRun = app(AgentThreadService::class)->run($thread, dispatch: false);

        $agent->response_sample = StringHelper::safeJsonDecode($threadRun->lastMessage->content, 999999);
        $agent->save();

        // Clean up the thread so we don't clutter the UI
        $thread->delete();

        return true;
    }
}
