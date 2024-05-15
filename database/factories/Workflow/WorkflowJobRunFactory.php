<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowJobRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status'       => fake()->randomElement(WorkflowRun::STATUSES),
            'started_at'   => fake()->boolean ? fake()->dateTime : null,
            'completed_at' => fake()->boolean ? fake()->dateTime : null,
            'failed_at'    => fake()->boolean ? fake()->dateTime : null,
        ];
    }
}
