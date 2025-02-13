<?php

namespace Database\Factories\Task;

use Illuminate\Database\Eloquent\Factories\Factory;

class TaskWorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name,
        ];
    }
}
