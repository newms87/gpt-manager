<?php

namespace Database\Factories\Workflow;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowInputFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name'    => fake()->unique()->name,
            'content' => fake()->sentence,
            'data'    => [],
            'tokens'  => fake()->numberBetween(1, 1000),
        ];
    }
}
