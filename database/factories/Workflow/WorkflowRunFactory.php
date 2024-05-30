<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id'       => Workflow::factory(),
            'workflow_input_id' => WorkflowInput::factory(),
            'status'            => WorkflowRun::STATUS_PENDING,
            'started_at'        => null,
            'completed_at'      => null,
            'failed_at'         => null,
        ];
    }

    public function started(): self
    {
        return $this->state(['started_at' => now()]);
    }

    public function completed(): self
    {
        return $this->state(['started_at' => now(), 'completed_at' => now()]);
    }

    public function failed(): self
    {
        return $this->state(['started_at' => now(), 'failed_at' => now()]);
    }
}
