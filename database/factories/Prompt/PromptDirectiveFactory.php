<?php

namespace Database\Factories\Prompt;

use App\Models\Schema\SchemaDefinition;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchemaDefinition>
 */
class PromptDirectiveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'        => Team::factory(),
            'name'           => fake()->unique()->firstName,
            'directive_text' => fake()->paragraph,
        ];
    }
}
