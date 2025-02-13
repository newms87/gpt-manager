<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskWorkflowNodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_workflow_id'   => TaskWorkflow::factory(),
            'task_definition_id' => TaskDefinition::factory(),
            'name'               => fake()->unique()->name,
            'settings'           => [],
            'params'             => [],
        ];
    }
}
