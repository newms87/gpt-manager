<?php

namespace Database\Factories\ContentSource;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContentSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name,
        ];
    }
}
