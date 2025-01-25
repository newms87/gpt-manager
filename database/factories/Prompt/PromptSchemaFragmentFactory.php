<?php

namespace Database\Factories\Prompt;

use Illuminate\Database\Eloquent\Factories\Factory;

class PromptSchemaFragmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->sentence(),
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
