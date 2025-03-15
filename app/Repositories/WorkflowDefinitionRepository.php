<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Exception;
use Illuminate\Database\Eloquent\Builder;
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
        $workflowDefinition->fill($data)->validate()->save($data);

        return $workflowDefinition;
    }

    /**
     * Add a node to a workflow definition
     */
    public function addNode(WorkflowDefinition $workflowDefinition, ?array $input = []): WorkflowNode
    {
        // First we must guarantee we have an agent (from the users team) selected to add to the definition
        $taskDefinition = team()->taskDefinitions()->find($input['task_definition_id'] ?? null);

        if (!$taskDefinition) {
            throw new Exception("You must choose a task definition to create task node.");
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
