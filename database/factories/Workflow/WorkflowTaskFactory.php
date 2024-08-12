<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group'        => '',
            'status'       => WorkflowRun::STATUS_PENDING,
            'started_at'   => null,
            'completed_at' => null,
            'failed_at'    => null,
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
