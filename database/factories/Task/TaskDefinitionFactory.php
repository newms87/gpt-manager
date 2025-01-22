<?php

namespace Database\Factories\Task;

use App\Models\Team\Team;
use App\Services\Task\TaskRunner;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'                => Team::factory(),
            'name'                   => fake()->unique()->name,
            'description'            => fake()->sentence,
            'task_service'           => TaskRunner::class,
            'input_grouping'         => null,
            'input_group_chunk_size' => 1,
        ];
    }
}
