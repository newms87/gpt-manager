<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowRunFactory extends Factory
{
    protected $model = WorkflowRun::class;

    public function definition(): array
    {
        return [
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'name'                   => $this->faker->words(3, true),
            'status'                 => 'pending',
            'started_at'             => now(),
        ];
    }
}
