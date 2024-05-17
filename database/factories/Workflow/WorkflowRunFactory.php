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
            'status'          => WorkflowRun::STATUS_PENDING,
            'started_at'      => null,
            'completed_at'    => null,
            'failed_at'       => null,
        ];
    }
}
