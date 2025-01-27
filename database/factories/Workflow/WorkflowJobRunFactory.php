<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowJobRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_job_id' => WorkflowJob::factory(),
            'workflow_run_id' => WorkflowRun::factory(),
            'status'          => WorkflowRun::STATUS_PENDING,
            'started_at'      => null,
            'completed_at'    => null,
            'failed_at'       => null,
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

    public function withArtifact(Artifact $artifact): static
    {
        return $this->afterCreating(fn(WorkflowJobRun $workflowJobRun) => $workflowJobRun->artifacts()->save($artifact));
    }

    public function withArtifactData(array $artifactData): static
    {
        return $this->afterCreating(function (WorkflowJobRun $workflowJobRun) use ($artifactData) {
            $artifacts = [];
            foreach($artifactData as $data) {
                $artifacts[] = Artifact::factory()->create(['json_content' => $data]);
            }
            $workflowJobRun->artifacts()->saveMany($artifacts);
        });
    }
}
