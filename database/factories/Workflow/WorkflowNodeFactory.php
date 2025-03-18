<?php

namespace Database\Factories\Workflow;

use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowDefinition;
use App\Services\Task\Runners\WorkflowInputTaskRunner;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowNodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'task_definition_id'     => TaskDefinition::factory(),
            'name'                   => fake()->unique()->name,
            'settings'               => [],
            'params'                 => [],
        ];
    }

    public function startingNode(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'task_definition_id' => TaskDefinition::factory()->create([
                    'task_runner_class' => WorkflowInputTaskRunner::RUNNER_NAME,
                ]),
            ];
        });
    }
}
