<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Agent;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Feature\Api\TestAi\TestAiApi;

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
            'api'          => TestAiApi::$serviceName,
            'model'        => 'test-model',
            'tools'        => fake()->randomElements(collect(config('ai.tools'))->pluck('name')->toArray()),
            'temperature'  => 0,
            'prompt'       => fake()->paragraphs(10, true),
        ];
    }
}
