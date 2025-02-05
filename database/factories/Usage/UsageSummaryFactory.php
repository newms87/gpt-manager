<?php

namespace Database\Factories\Usage;

use App\Models\Task\TaskProcess;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageSummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'object_type'   => TaskProcess::class,
            'object_id'     => TaskProcess::factory(),
            'count'         => 1,
            'run_time_ms'   => fake()->numberBetween(100, 1000),
            'input_tokens'  => fake()->numberBetween(100, 1000),
            'output_tokens' => fake()->numberBetween(100, 1000),
            'input_cost'    => fake()->randomFloat(4, 0, 1000),
            'output_cost'   => fake()->randomFloat(4, 0, 1000),
        ];
    }
}
