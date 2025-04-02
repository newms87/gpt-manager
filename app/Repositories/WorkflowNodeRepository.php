<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowNode;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowNodeRepository extends ActionRepository
{
    public static string $model = WorkflowNode::class;

    /**
     * @inheritdoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'copy' => $this->copyNode($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Copies a WorkflowNode and creates a copied TaskDefinition w/ associated schema definitions
     * The copied node is still associated to the same workflow.
     */
    public function copyNode(WorkflowNode $workflowNode): WorkflowNode
    {
        $newTaskDefinition       = $workflowNode->taskDefinition->replicate(['task_run_count']);
        $newTaskDefinition->name = ModelHelper::getNextModelName($newTaskDefinition);
        $newTaskDefinition->save();

        foreach($workflowNode->taskDefinition->schemaAssociations as $schemaAssociation) {
            $newTaskDefinition->schemaAssociations()->create([
                'schema_definition_id' => $schemaAssociation->schema_definition_id,
                'schema_fragment_id'   => $schemaAssociation->schema_fragment_id,
            ]);
        }

        $newWorkflowNode                     = $workflowNode->replicate();
        $newWorkflowNode->name               = $newTaskDefinition->name;
        $newWorkflowNode->task_definition_id = $newTaskDefinition->id;

        $settings = $newWorkflowNode->settings;
        if (isset($settings['x'])) {
            $settings['x']             += 250;
            $newWorkflowNode->settings = $settings;
        }

        $newWorkflowNode->save();

        return $newWorkflowNode;
    }
}
