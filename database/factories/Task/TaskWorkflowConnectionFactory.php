<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskWorkflow;
use App\Models\Task\TaskWorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskWorkflowConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_workflow_id'   => TaskWorkflow::factory(),
            'source_node_id'     => TaskWorkflowNode::factory(),
            'target_node_id'     => TaskWorkflowNode::factory(),
            'source_output_port' => 'Main',
            'target_input_port'  => 'Main',
            'name'               => fake()->name,
        ];
    }
}
