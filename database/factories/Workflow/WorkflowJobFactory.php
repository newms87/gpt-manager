<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workflow\WorkflowJob>
 */
class WorkflowJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'name'        => fake()->unique()->name,
            'description' => fake()->sentence,
            'depends_on'  => null,
        ];
    }
}
