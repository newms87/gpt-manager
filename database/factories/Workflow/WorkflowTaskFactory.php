<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status'       => fake()->randomElement(WorkflowTask::STATUSES),
            'started_at'   => fake()->boolean ? fake()->dateTime : null,
            'completed_at' => fake()->boolean ? fake()->dateTime : null,
            'failed_at'    => fake()->boolean ? fake()->dateTime : null,
        ];
    }
}
