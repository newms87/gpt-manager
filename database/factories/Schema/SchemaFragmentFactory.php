<?php

namespace Database\Factories\Schema;

use App\Models\Schema\SchemaDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchemaFragmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schema_definition_id' => SchemaDefinition::factory(),
            'name'                 => fake()->sentence(),
            'fragment_selector'    => [
                'type'     => 'object',
                'children' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
