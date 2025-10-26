<?php

namespace Database\Factories\Workflow;

use App\Models\Agent\AgentThread;
use App\Models\Team\Team;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderChat>
 */
class WorkflowBuilderChatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'                 => Team::factory(),
            'workflow_input_id'       => fn(array $attributes) => WorkflowInput::factory()->recycle(Team::find($attributes['team_id']))->create(),
            'workflow_definition_id'  => fn(array $attributes) => WorkflowDefinition::factory()->recycle(Team::find($attributes['team_id']))->create(),
            'agent_thread_id'         => fn(array $attributes) => AgentThread::factory()->recycle(Team::find($attributes['team_id']))->create(),
            'status'                  => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING,
            'meta'                    => [],
            'current_workflow_run_id' => null,
        ];
    }

    public function withStatus(string $status): static
    {
        return $this->state([
            'status' => $status,
        ]);
    }

    public function withMeta(array $meta): static
    {
        return $this->state([
            'meta' => $meta,
        ]);
    }

    public function withCurrentWorkflowRun(?WorkflowRun $workflowRun = null): static
    {
        if (!$workflowRun) {
            return $this->afterCreating(function (WorkflowBuilderChat $chat) {
                $workflowRun = WorkflowRun::factory()->create([
                    'workflow_definition_id' => $chat->workflow_definition_id,
                ]);
                $chat->update(['current_workflow_run_id' => $workflowRun->id]);
            });
        }

        return $this->state([
            'current_workflow_run_id' => $workflowRun->id,
        ]);
    }

    public function analyzing(): static
    {
        return $this->withStatus(WorkflowBuilderChat::STATUS_ANALYZING_PLAN);
    }

    public function building(): static
    {
        return $this->withStatus(WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW);
    }

    public function evaluating(): static
    {
        return $this->withStatus(WorkflowBuilderChat::STATUS_EVALUATING_RESULTS);
    }

    public function completed(): static
    {
        return $this->withStatus(WorkflowBuilderChat::STATUS_COMPLETED);
    }

    public function failed(): static
    {
        return $this->withStatus(WorkflowBuilderChat::STATUS_FAILED);
    }

    public function withArtifacts(?array $artifacts = null): static
    {
        if ($artifacts === null) {
            $artifacts = [
                'workflow_definition' => [
                    'name'  => fake()->words(3, true),
                    'nodes' => [
                        [
                            'id'   => fake()->uuid(),
                            'type' => 'task',
                            'name' => fake()->words(2, true),
                        ],
                    ],
                ],
                'generated_at' => now()->toISOString(),
            ];
        }

        return $this->withMeta([
            'artifacts'            => $artifacts,
            'artifacts_updated_at' => now()->toISOString(),
        ]);
    }

    public function withBuildState(?array $buildState = null): static
    {
        if ($buildState === null) {
            $buildState = [
                'current_phase'   => 'requirements_analysis',
                'progress'        => 25,
                'steps_completed' => ['requirements_gathered'],
                'next_steps'      => ['analyze_requirements', 'create_workflow_structure'],
            ];
        }

        return $this->withMeta([
            'build_state' => $buildState,
            'phase_data'  => [],
            'updated_at'  => now()->toISOString(),
        ]);
    }
}
