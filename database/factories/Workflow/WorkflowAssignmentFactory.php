<?php

namespace Database\Factories\Workflow;

use App\Models\Agent\Agent;
use App\Models\Workflow\WorkflowJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_job_id' => WorkflowJob::factory(),
            'agent_id'        => Agent::factory(),
            'is_required'     => fake()->boolean,
            'max_attempts'    => fake()->numberBetween(1, 3),
        ];
    }
}
