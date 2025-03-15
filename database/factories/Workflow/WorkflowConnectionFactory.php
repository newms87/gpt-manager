<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'source_node_id'         => WorkflowNode::factory(),
            'target_node_id'         => WorkflowNode::factory(),
            'source_output_port'     => 'Main',
            'target_input_port'      => 'Main',
            'name'                   => fake()->name,
        ];
    }

    public function connect(WorkflowDefinition $workflowDefinition, WorkflowNode $sourceNode, WorkflowNode $targetNode): self
    {
        return $this->state([
            'workflow_definition_id' => $workflowDefinition->id,
            'source_node_id'         => $sourceNode->id,
            'target_node_id'         => $targetNode->id,
        ]);
    }
}
