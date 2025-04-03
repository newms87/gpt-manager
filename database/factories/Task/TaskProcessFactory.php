<?php

namespace Database\Factories\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskProcessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_run_id'     => TaskRun::factory(),
            'agent_thread_id' => null,
            'name'            => fake()->unique()->name,
            'started_at'      => null,
            'stopped_at'      => null,
            'failed_at'       => null,
            'completed_at'    => null,
            'timeout_at'      => null,
        ];
    }

    public function withAgent(Agent $agent = null): static
    {
        return $this->afterCreating(function (TaskProcess $taskProcess) use ($agent) {
            $taskDefinition = $taskProcess->taskRun->taskDefinition;
            $taskDefinition->agent()->associate($agent ?: Agent::factory()->create())->save();
            $taskDefinition->save();
        });
    }

    public function withInputArtifacts($attributes = [], $files = []): static
    {
        return $this->afterCreating(function (TaskProcess $taskProcess) use ($attributes, $files) {
            $artifacts = Artifact::factory()->hasStoredFiles(count($files), $files)->create($attributes);
            $taskProcess->inputArtifacts()->saveMany($artifacts instanceof Artifact ? [$artifacts] : $artifacts);
        });
    }
}
