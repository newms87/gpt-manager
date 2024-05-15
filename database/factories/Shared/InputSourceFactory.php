<?php

namespace Database\Factories\Shared;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InputSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name'    => fake()->unique()->name,
            'data'    => [
                [
                    'name'  => 'message',
                    'type'  => 'text',
                    'value' => fake()->sentence,
                ],
            ],
            'tokens'  => fake()->numberBetween(1, 1000),
        ];
    }
}
