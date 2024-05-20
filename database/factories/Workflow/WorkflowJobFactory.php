<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowJob>
 */
class WorkflowJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id'      => Workflow::factory(),
            'name'             => fake()->unique()->name,
            'description'      => fake()->sentence,
            'use_input_source' => true,
        ];
    }
}
