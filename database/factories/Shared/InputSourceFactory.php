<?php

namespace Database\Factories\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;

class InputSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => fake()->unique()->name,
            'data'   => [
                [
                    'name'  => 'message',
                    'type'  => 'text',
                    'value' => fake()->sentence,
                ],
            ],
            'tokens' => fake()->numberBetween(1, 1000),
        ];
    }
}
