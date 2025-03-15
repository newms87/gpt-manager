<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Agent;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'      => Team::factory(),
            'knowledge_id' => null,
            'name'         => fake()->unique()->firstName,
            'description'  => fake()->paragraph,
            'model'        => 'test-model',
            'tools'        => null,
            'temperature'  => 0,
        ];
    }
}
