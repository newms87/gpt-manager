<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Agent;
use App\Models\Team\Team;
use App\Repositories\AgentRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        $models = AgentRepository::getAiModels();

        $model = fake()->randomElement($models);

        return [
            'team_id'      => Team::factory(),
            'knowledge_id' => null,
            'name'         => fake()->unique()->firstName,
            'description'  => fake()->paragraph,
            'api'          => $model['api'],
            'model'        => $model['name'],
            'tools'        => fake()->randomElements(collect(config('ai.tools'))->pluck('name')->toArray()),
            'temperature'  => 0,
            'prompt'       => fake()->paragraphs(10, true),
        ];
    }
}
