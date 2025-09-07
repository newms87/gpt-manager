<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowListener;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowListener>
 */
class WorkflowListenerFactory extends Factory
{
    protected $model = WorkflowListener::class;

    public function definition(): array
    {
        return [
            'team_id' => null, // Must be provided
            'workflow_run_id' => null, // Must be provided
            'listener_type' => null, // Must be provided
            'listener_id' => null, // Must be provided
            'workflow_type' => WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER,
            'status' => WorkflowListener::STATUS_PENDING,
            'metadata' => [],
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowListener::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowListener::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowListener::STATUS_FAILED,
            'started_at' => now()->subMinutes(10),
            'failed_at' => now(),
        ]);
    }

    public function extractData(): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_type' => WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);
    }

    public function writeDemandLetter(): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_type' => WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER,
        ]);
    }
}