<?php

namespace App\Repositories;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionAgent;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TaskDefinitionRepository extends ActionRepository
{
    public static string $model = TaskDefinition::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(task_run_count) as task_run_count"),
            DB::raw("SUM(task_agent_count) as task_agent_count"),
        ]);
    }

    public function fieldOptions(?array $filter = []): array
    {
        $runners = [
            ['label' => AgentThreadTaskRunner::RUNNER_NAME, 'value' => AgentThreadTaskRunner::RUNNER_NAME],
        ];

        return [
            'runners' => $runners,
        ];
    }

    /**
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createTaskDefinition($data),
            'update' => $this->updateTaskDefinition($model, $data),
            'copy' => $this->copyTaskDefinition($model),
            'add-agent' => $this->addAgent($model, $data),
            'update-agent' => $this->updateAgent($model, $data),
            'copy-agent' => $this->copyAgent($model, $data),
            'remove-agent' => $this->removeAgent($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a task definition applying business rules
     */
    public function createTaskDefinition(array $data): TaskDefinition
    {
        $taskDefinition = TaskDefinition::make()->forceFill([
            'team_id' => team()->id,
        ]);

        $data += [
            'description'           => '',
            'task_runner_class'     => AgentThreadTaskRunner::RUNNER_NAME,
            'timeout_after_seconds' => 300,
        ];

        return $this->updateTaskDefinition($taskDefinition, $data);
    }

    /**
     * Update a task definition applying business rules
     */
    public function updateTaskDefinition(TaskDefinition $taskDefinition, array $data): TaskDefinition
    {
        $taskDefinition->fill($data)->validate()->save($data);

        return $taskDefinition;
    }

    /**
     * Copy a task definition
     */
    public function copyTaskDefinition(TaskDefinition $taskDefinition): TaskDefinition
    {
        $newTaskDefinition       = $taskDefinition->replicate(['task_run_count', 'task_agent_count']);
        $newTaskDefinition->name = ModelHelper::getNextModelName($taskDefinition);
        $newTaskDefinition->save();

        return $newTaskDefinition;
    }

    /**
     * Add an agent to a task definition
     */
    public function addAgent(TaskDefinition $taskDefinition, ?array $input = []): TaskDefinitionAgent
    {
        // First we must guarantee we have an agent (from the users team) selected to add to the definition
        $agent = team()->agents()->find($input['agent_id'] ?? null);

        if (!$agent) {
            $agent = team()->agents()->first();
            if (!$agent) {
                throw new Exception("You must create an agent before adding an agent to a task definition.");
            }
            $input['agent_id'] = $agent->id;
        }

        return $taskDefinition->definitionAgents()->create($input ?? []);
    }

    /**
     * Update an agent in a task definition
     */
    public function updateAgent(TaskDefinition $taskDefinition, ?array $input = []): TaskDefinitionAgent
    {
        $taskDefinitionAgent = $taskDefinition->definitionAgents()->find($input['id']);

        if (!$taskDefinitionAgent) {
            throw new Exception("TaskDefinitionAgent not found: $input[id]");
        }

        $taskDefinitionAgent->fill($input)->save();

        return $taskDefinitionAgent;
    }

    /**
     * Copy an agent in a task definition
     */
    public function copyAgent(TaskDefinition $taskDefinition, ?array $input = []): TaskDefinitionAgent
    {
        $taskDefinitionAgent = $taskDefinition->definitionAgents()->find($input['id']);

        if (!$taskDefinitionAgent) {
            throw new Exception("TaskDefinitionAgent not found: $input[id]");
        }

        $replicateAgent = $taskDefinitionAgent->replicate();
        $replicateAgent->save();

        return $replicateAgent;
    }

    /**
     * Remove an agent from a task definition
     */
    public function removeAgent(TaskDefinition $taskDefinition, ?array $input = []): bool
    {
        $taskDefinitionAgent = $taskDefinition->definitionAgents()->find($input['id']);

        if (!$taskDefinitionAgent) {
            throw new Exception("TaskDefinitionAgent not found: $input[id]");
        }

        $taskDefinitionAgent->delete();

        return true;
    }
}
