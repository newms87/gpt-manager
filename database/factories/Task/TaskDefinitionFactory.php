<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionAgent;
use App\Models\Team\Team;
use App\Services\Task\Runners\TaskRunnerBase;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'                => Team::factory(),
            'name'                   => fake()->unique()->name,
            'description'            => fake()->sentence,
            'task_runner_class'      => TaskRunnerBase::class,
            'input_grouping'         => null,
            'input_group_chunk_size' => 1,
        ];
    }

    public function withDefinitionAgent($attributes = [], $count = 1): TaskDefinitionFactory
    {
        return $this->afterCreating(function (TaskDefinition $taskDefinition) use ($attributes, $count) {
            $definitionAgent = TaskDefinitionAgent::factory()->count($count)->create($attributes);
            $taskDefinition->definitionAgents()->saveMany($definitionAgent);
        });
    }
}
