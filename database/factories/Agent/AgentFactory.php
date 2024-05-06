<?php

namespace Database\Factories\Agent;

use App\Api\OpenAi\OpenAiApi;
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
        $models = [
            ['id' => 'gpt-4-turbo', 'api' => OpenAiApi::$serviceName],
            ['id' => 'gpt-4', 'api' => OpenAiApi::$serviceName],
            ['id' => 'gpt-3.5-turbo', 'api' => OpenAiApi::$serviceName],
            ['id' => 'claude-opus', 'api' => 'Anthropic'],
        ];

        $model = fake()->randomElement($models);

        return [
            'team_id'      => Team::factory(),
            'knowledge_id' => null,
            'name'         => fake()->unique()->firstName,
            'description'  => fake()->paragraph,
            'api'          => $model['api'],
            'model'        => $model['id'],
            'tools'        => fake()->randomElements(collect(config('ai.tools'))->pluck('name')->toArray()),
            'temperature'  => 0,
            'prompt'       => fake()->paragraphs(10, true),
        ];
    }
}
