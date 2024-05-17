<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status'       => WorkflowTask::STATUS_PENDING,
            'started_at'   => null,
            'completed_at' => null,
            'failed_at'    => null,
        ];
    }
}
