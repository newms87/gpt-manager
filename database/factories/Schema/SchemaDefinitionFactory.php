<?php

namespace Database\Factories\Schema;

use App\Models\Schema\SchemaDefinition;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchemaDefinition>
 */
class SchemaDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'          => Team::factory(),
            'type'             => SchemaDefinition::TYPE_AGENT_RESPONSE,
            'name'             => fake()->unique()->firstName,
            'description'      => fake()->sentence,
            'schema_format'    => SchemaDefinition::FORMAT_JSON,
            'schema'           => null,
            'response_example' => null,
        ];
    }

    public function withJsonSchema(): self
    {
        return $this->state([
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'name'  => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ],
        ]);
    }
}
