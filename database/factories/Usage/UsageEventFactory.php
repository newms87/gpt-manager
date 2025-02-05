<?php

namespace Database\Factories\Usage;

use App\Models\Task\TaskProcess;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'       => Team::factory(),
            'user_id'       => User::factory(),
            'object_type'   => TaskProcess::class,
            'object_id'     => TaskProcess::factory(),
            'event_type'    => 'Agent Thread Run',
            'run_time_ms'   => fake()->numberBetween(100, 1000),
            'input_tokens'  => fake()->numberBetween(100, 1000),
            'output_tokens' => fake()->numberBetween(100, 1000),
            'input_cost'    => fake()->randomFloat(4, 0, 1000),
            'output_cost'   => fake()->randomFloat(4, 0, 1000),
        ];
    }
}
