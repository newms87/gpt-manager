<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\WorkflowTools\RunAgentThreadWorkflowTool;
use App\WorkflowTools\WorkflowInputWorkflowTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowJob>
 */
class WorkflowJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id'   => Workflow::factory(),
            'name'          => fake()->unique()->name,
            'description'   => fake()->sentence,
            'workflow_tool' => RunAgentThreadWorkflowTool::class,
        ];
    }

    public function isWorkflowInputTool(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'workflow_tool' => WorkflowInputWorkflowTool::class,
            ];
        });
    }

    public function dependsOn(array $dependencyJobs): static
    {
        return $this->afterCreating(function (WorkflowJob $job) use ($dependencyJobs) {
            foreach($dependencyJobs as $dependencyJob) {
                $job->dependencies()->create([
                    'depends_on_workflow_job_id' => $dependencyJob->id,
                ]);
            }
        });
    }
}
