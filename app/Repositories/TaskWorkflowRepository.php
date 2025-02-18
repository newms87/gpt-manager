<?php

namespace App\Repositories;

use App\Models\Task\TaskWorkflow;
use App\Models\Task\TaskWorkflowConnection;
use App\Models\Task\TaskWorkflowNode;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TaskWorkflowRepository extends ActionRepository
{
    public static string $model = TaskWorkflow::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    /**
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createTaskWorkflow($data ?? []),
            'update' => $this->updateTaskWorkflow($model, $data),
            'add-node' => $this->addNode($model, $data),
            'add-connection' => $this->addConnection($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a task workflow applying business rules
     */
    public function createTaskWorkflow(array $input = []): TaskWorkflow
    {
        $taskWorkflow = TaskWorkflow::make()->forceFill([
            'team_id' => team()->id,
            'name'    => 'New Task Workflow',
        ]);

        $taskWorkflow->name = ModelHelper::getNextModelName($taskWorkflow);


        return $this->updateTaskWorkflow($taskWorkflow, $input);
    }

    /**
     * Update a task workflow applying business rules
     */
    public function updateTaskWorkflow(TaskWorkflow $taskWorkflow, array $data): TaskWorkflow
    {
        $taskWorkflow->fill($data)->validate()->save($data);

        return $taskWorkflow;
    }

    /**
     * Add a node to a task workflow
     */
    public function addNode(TaskWorkflow $taskWorkflow, ?array $input = []): TaskWorkflowNode
    {
        // First we must guarantee we have an agent (from the users team) selected to add to the definition
        $taskDefinition = team()->taskDefinitions()->find($input['task_definition_id'] ?? null);

        if (!$taskDefinition) {
            throw new Exception("You must choose a task definition to create task node.");
        }

        return $taskWorkflow->taskWorkflowNodes()->create([
            'task_definition_id' => $taskDefinition->id,
            'name'               => $input['name'] ?? $taskDefinition->name,
            'settings'           => $input['settings'] ?? null,
            'params'             => $input['params'] ?? null,
        ]);
    }

    /**
     * Add a connection to a task workflow
     */
    public function addConnection(TaskWorkflow $taskWorkflow, ?array $input = []): TaskWorkflowConnection
    {
        $sourceNode = $taskWorkflow->taskWorkflowNodes()->find($input['source_node_id'] ?? null);
        $targetNode = $taskWorkflow->taskWorkflowNodes()->find($input['target_node_id'] ?? null);

        if (!$sourceNode || !$targetNode) {
            throw new Exception("You must choose a source and target node to create a connection.");
        }

        return $taskWorkflow->taskWorkflowConnections()->create([
            'source_node_id'     => $sourceNode->id,
            'target_node_id'     => $targetNode->id,
            'source_output_port' => $input['source_output_port'] ?? 'default',
            'target_input_port'  => $input['target_input_port'] ?? 'default',
            'settings'           => $input['settings'] ?? null,
            'params'             => $input['params'] ?? null,
        ]);
    }
}
