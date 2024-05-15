<?php

namespace Database\Factories\Workflow;

use App\Models\Shared\InputSource;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id'     => Workflow::factory(),
            'input_source_id' => InputSource::factory(),
            'status'          => fake()->randomElement(WorkflowRun::STATUSES),
            'started_at'      => fake()->boolean ? fake()->dateTime : null,
            'completed_at'    => fake()->boolean ? fake()->dateTime : null,
            'failed_at'       => fake()->boolean ? fake()->dateTime : null,
        ];
    }
}
