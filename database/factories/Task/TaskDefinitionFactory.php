<?php

namespace Database\Factories\Task;

use App\Models\Team\Team;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'                => Team::factory(),
            'name'                   => fake()->unique()->name,
            'description'            => fake()->sentence,
            'task_runner_class'      => AgentThreadTaskRunner::class,
            'input_grouping'         => null,
            'input_group_chunk_size' => 1,
        ];
    }
}
