<?php

namespace Database\Factories\Prompt;

use App\Models\Prompt\PromptSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromptSchemaFragmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prompt_schema_id'  => PromptSchema::factory(),
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
