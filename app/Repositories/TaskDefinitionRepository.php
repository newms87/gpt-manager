<?php

namespace App\Repositories;

use App\Models\Schema\SchemaAssociation;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskInput;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TaskDefinitionRepository extends ActionRepository
{
    public static string $model = TaskDefinition::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id)->whereNull('resource_package_import_id');
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
        $runners = [];
        foreach(config('ai.runners') as $runnerName => $runnerClass) {
            $runners[] = [
                'label' => $runnerName,
                'value' => $runnerName,
            ];
        }

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
            'add-fragment' => $this->addFragment($model, $data),
            'add-agent' => $this->addAgent($model, $data),
            'add-input' => $this->addInput($model, $data),
            'remove-input' => $this->removeInput($model, $data),
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
     * Add a schema fragment to a task definition via a schema association
     */
    public function addFragment(TaskDefinition $taskDefinition, array $input = []): SchemaAssociation
    {
        if (!$taskDefinition->schema_definition_id) {
            throw new ValidationError("Failed to add fragment: First add a schema definition to the task.");
        }

        return $taskDefinition->schemaAssociations()->create([
            'schema_definition_id' => $taskDefinition->schema_definition_id,
            'schema_fragment_id'   => $input['schema_fragment_id'] ?? null,
        ]);
    }

    /**
     * Add a task input to a task definition to enable running the task against the input
     */
    public function addInput(TaskDefinition $taskDefinition, ?array $input = []): TaskInput
    {
        $workflowInput = team()->workflowInputs()->find($input['workflow_input_id'] ?? null);

        if (!$workflowInput) {
            throw new ValidationError("The workflow input was not found.");
        }

        if ($taskDefinition->taskInputs()->where('workflow_input_id', $workflowInput->id)->exists()) {
            throw new ValidationError("The task input already exists for this task definition.");
        }

        return $taskDefinition->taskInputs()->create([
            'workflow_input_id' => $workflowInput->id,
        ]);
    }

    /**
     * Remove a task input from a task definition
     */
    public function removeInput(TaskDefinition $taskDefinition, ?array $input = []): bool
    {
        $taskInput = $taskDefinition->taskInputs()->find($input['id']);

        if (!$taskInput) {
            throw new Exception("TaskInput not found: $input[id]");
        }

        $taskInput->delete();

        return true;
    }
}
