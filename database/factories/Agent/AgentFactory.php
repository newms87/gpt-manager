<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Knowledge;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id'      => Team::factory(),
            'knowledge_id' => Knowledge::factory(),
            'name'         => fake()->firstName,
            'description'  => fake()->paragraph(2),
            'model'        => 'gpt-4-turbo',
            'functions'    => [],
            'temperature'  => 0,
            'prompt'       => fake()->paragraphs(10, true),
        ];
    }
}
