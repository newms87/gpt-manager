<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\Prompt\PromptDirective;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
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
        $aiModels = collect(static::getAiModels())->sortKeys()->map(function ($aiModel) {
            $million = 1000000;
            $input   = $aiModel['details']['input'] * $million;
            $output  = $aiModel['details']['output'] * $million;

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
            'save-directive' => $this->saveDirective($model, $data['agent_prompt_directive_id'] ?? null, $data),
            'update-directives' => $this->updateDirectives($model, $data['directives'] ?? []),
            'remove-directive' => $this->removeDirective($model, $data['id'] ?? null),
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
            'retry_count' => 2,
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

    /**
     * Add / Update a directive to an agent
     */
    public function saveDirective(Agent $agent, $agentPromptDirectiveId, array $input): AgentPromptDirective
    {
        if ($agentPromptDirectiveId) {
            $agentPromptDirective = $agent->directives()->find($agentPromptDirectiveId);

            if (!$agentPromptDirective) {
                throw new ValidationError("Agent Directive with ID $agentPromptDirectiveId not found");
            }
        } else {
            $agentPromptDirective = $agent->directives()->make();
        }

        $promptDirectiveId = $input['prompt_directive_id'] ?? null;
        $section           = $input['section'] ?? ($agentPromptDirective?->section ?: AgentPromptDirective::SECTION_TOP);
        $position          = $input['position'] ?? ($agentPromptDirective?->position ?: 0);
        $name              = $input['name'] ?? '';

        // Resolve or create the prompt directive
        if ($promptDirectiveId) {
            $promptDirective = PromptDirective::where('team_id', team()->id)->find($promptDirectiveId);
            if (!$promptDirective) {
                throw new ValidationError('Prompt Directive not found: ' . $promptDirectiveId);
            }
        } else {
            // Lookup the prompt directive by name to see if there is a matching directive
            $promptDirective              = PromptDirective::where('name', $name)->first();
            $existingAgentPromptDirective = null;

            // If the prompt directive exists, maybe it already is associated to the agent?
            if ($promptDirective) {
                $existingAgentPromptDirective = $agent->directives()->where('prompt_directive_id', $promptDirective->id)->first();
            }

            // If there was no matching prompt directive or the prompt directive has already been associated to the agent, just create a new one as the user requested
            if (!$promptDirective || $existingAgentPromptDirective) {
                $promptDirective = PromptDirective::make([
                    'team_id' => team()->id,
                    'name'    => $name,
                ]);

                $promptDirective->name = ModelHelper::getNextModelName($promptDirective);
                $promptDirective->save();
            }
        }

        $agentPromptDirective->fill([
            'prompt_directive_id' => $promptDirective->id,
            'section'             => $section,
            'position'            => $position,
        ])->save();

        return $agentPromptDirective;
    }

    /**
     * Update the order of directives in an agent
     */
    public function updateDirectives(Agent $agent, $agentDirectives): bool
    {
        foreach($agentDirectives as $position => $directive) {
            $agentDirective = $agent->directives()->find($directive['id']);

            if (!$agentDirective) {
                throw new ValidationError("Directive with ID $directive[id] not found");
            }

            $agentDirective->update([
                'section'  => $directive['section'],
                'position' => $position,
            ]);
        }

        return true;
    }

    /**
     * Remove a directive from an agent
     */
    public function removeDirective(Agent $agent, $directiveId): bool
    {
        $agent->directives()->where('prompt_directive_id', $directiveId)->delete();

        return true;
    }
}
