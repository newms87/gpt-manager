<?php

namespace App\Repositories;

use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowDefinitionRepository extends ActionRepository
{
    public static string $model = WorkflowDefinition::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    /**
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        // Prevent modifying system workflows
        if ($model && $model->team_id === null && in_array($action, ['update', 'delete', 'add-node', 'add-connection'])) {
            throw new ValidationError('System workflows cannot be modified');
        }

        return match ($action) {
            'create' => $this->createWorkflowDefinition($data ?? []),
            'update' => $this->updateWorkflowDefinition($model, $data),
            'add-node' => $this->addNode($model, $data),
            'add-connection' => $this->addConnection($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a workflow applying business rules
     */
    public function createWorkflowDefinition(array $input = []): WorkflowDefinition
    {
        $workflowDefinition = WorkflowDefinition::make()->forceFill([
            'team_id' => team()->id,
            'name'    => 'New Workflow Definition',
        ]);

        $workflowDefinition->name = ModelHelper::getNextModelName($workflowDefinition);


        return $this->updateWorkflowDefinition($workflowDefinition, $input);
    }

    /**
     * Update a workflow applying business rules
     */
    public function updateWorkflowDefinition(WorkflowDefinition $workflowDefinition, array $data): WorkflowDefinition
    {
        // Prevent modifying system workflows
        if ($workflowDefinition->team_id === null) {
            throw new ValidationError('System workflows cannot be modified');
        }

        $workflowDefinition->fill($data)->validate()->save($data);

        return $workflowDefinition;
    }

    /**
     * Add a node to a workflow definition
     */
    public function addNode(WorkflowDefinition $workflowDefinition, ?array $input = []): WorkflowNode
    {
        // Prevent modifying system workflows
        if ($workflowDefinition->team_id === null) {
            throw new ValidationError('System workflows cannot be modified');
        }

        // First we must guarantee we have an agent (from the users team) selected to add to the definition
        $taskDefinition = team()->taskDefinitions()->find($input['task_definition_id'] ?? null);
        $taskRunnerName = $input['task_runner_name'] ?? null;

        if (!$taskRunnerName && !$taskDefinition) {
            throw new ValidationError("You must choose a task definition or a task runner to create task node.");
        }

        if (!$taskDefinition) {
            $taskDefinition = TaskDefinition::make()->forceFill([
                'team_id'          => team()->id,
                'name'             => $taskRunnerName,
                'task_runner_name' => $taskRunnerName,
            ]);

            $taskDefinition->name = ModelHelper::getNextModelName($taskDefinition, 'name', ['team_id' => team()->id]);

            if (!$taskDefinition->getRunner()) {
                throw new ValidationError("Task runner class $taskRunnerName is not valid.");
            }

            $taskDefinition->save();
        }

        return $workflowDefinition->workflowNodes()->create([
            'task_definition_id' => $taskDefinition->id,
            'name'               => $input['name'] ?? $taskDefinition->name,
            'settings'           => $input['settings'] ?? null,
            'params'             => $input['params'] ?? null,
        ]);
    }

    /**
     * Add a connection to a workflow definition
     */
    public function addConnection(WorkflowDefinition $workflowDefinition, ?array $input = []): WorkflowConnection
    {
        // Prevent modifying system workflows
        if ($workflowDefinition->team_id === null) {
            throw new ValidationError('System workflows cannot be modified');
        }

        $sourceNode = $workflowDefinition->workflowNodes()->find($input['source_node_id'] ?? null);
        $targetNode = $workflowDefinition->workflowNodes()->find($input['target_node_id'] ?? null);

        if (!$sourceNode || !$targetNode) {
            throw new Exception("You must choose a source and target node to create a connection.");
        }

        return $workflowDefinition->workflowConnections()->create([
            'source_node_id'     => $sourceNode->id,
            'target_node_id'     => $targetNode->id,
            'source_output_port' => $input['source_output_port'] ?? 'default',
            'target_input_port'  => $input['target_input_port'] ?? 'default',
            'settings'           => $input['settings'] ?? null,
            'params'             => $input['params'] ?? null,
        ]);
    }
}
