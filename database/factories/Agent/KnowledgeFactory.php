<?php

namespace Database\Factories\Agent;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent\Knowledge>
 */
class KnowledgeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id'     => Team::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->paragraph(2),
        ];
    }
}
