<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskInputFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_definition_id' => TaskDefinition::factory(),
            'workflow_input_id'  => WorkflowInput::factory(),
        ];
    }
}
