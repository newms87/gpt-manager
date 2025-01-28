<?php

namespace Database\Factories\Workflow;

use App\Models\Agent\AgentThread;
use App\Models\User;
use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'                => User::factory(),
            'group'                  => '',
            'status'                 => WorkflowRun::STATUS_PENDING,
            'started_at'             => null,
            'completed_at'           => null,
            'failed_at'              => null,
            'workflow_job_id'        => WorkflowJob::factory(),
            'workflow_job_run_id'    => WorkflowJobRun::factory(),
            'workflow_assignment_id' => WorkflowAssignment::factory(),
            'thread_id'              => AgentThread::factory()->withMessages(),
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
