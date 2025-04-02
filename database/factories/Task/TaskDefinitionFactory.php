<?php

namespace Database\Factories\Task;

use App\Models\Team\Team;
use App\Services\Task\Runners\BaseTaskRunner;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'             => Team::factory(),
            'name'                => fake()->unique()->name,
            'description'         => fake()->sentence,
            'task_runner_class'   => BaseTaskRunner::RUNNER_NAME,
            'task_runner_config'  => null,
            'artifact_split_mode' => '',
        ];
    }
}
