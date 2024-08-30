<?php

namespace Database\Factories\Prompt;

use App\Models\Prompt\PromptSchema;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptSchema>
 */
class PromptSchemaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'          => Team::factory(),
            'type'             => PromptSchema::TYPE_AGENT_RESPONSE,
            'name'             => fake()->unique()->firstName,
            'description'      => fake()->sentence,
            'schema_format'    => PromptSchema::FORMAT_JSON,
            'schema'           => null,
            'response_example' => null,
        ];
    }
}
