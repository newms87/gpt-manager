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
     * Copies a WorkflowNode and creates a copied TaskDefinition w/ associated Agents + schema definitions
     * The copied node is still associated to the same workflow.
     */
    public function copyNode(WorkflowNode $workflowNode): WorkflowNode
    {
        $newTaskDefinition       = $workflowNode->taskDefinition->replicate(['task_run_count', 'task_agent_count']);
        $newTaskDefinition->name = ModelHelper::getNextModelName($newTaskDefinition);
        $newTaskDefinition->save();

        foreach($workflowNode->taskDefinition->definitionAgents as $definitionAgent) {
            $newDefinitionAgent                     = $definitionAgent->replicate();
            $newDefinitionAgent->task_definition_id = $newTaskDefinition->id;
            $newDefinitionAgent->save();

            $newOutputSchemaAssociation            = $definitionAgent->outputSchemaAssociation->replicate();
            $newOutputSchemaAssociation->object_id = $newDefinitionAgent->id;
            $newOutputSchemaAssociation->save();

            foreach($definitionAgent->inputSchemaAssociations as $inputSchemaAssociation) {
                $newInputSchemaAssociation            = $inputSchemaAssociation->replicate();
                $newInputSchemaAssociation->object_id = $newDefinitionAgent->id;
                $newInputSchemaAssociation->save();
            }
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
