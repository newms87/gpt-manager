<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptSchema;
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
            'team_id'            => Team::factory(),
            'knowledge_id'       => null,
            'name'               => fake()->unique()->firstName,
            'description'        => fake()->paragraph,
            'api'                => TestAiApi::$serviceName,
            'model'              => 'test-model',
            'tools'              => null,
            'temperature'        => 0,
            'response_format'    => Agent::RESPONSE_FORMAT_TEXT,
            'response_schema_id' => null,
        ];
    }

    public function withJsonSchemaResponse(PromptSchema $promptSchema = null): self
    {
        return $this->state([
            'response_format'    => Agent::RESPONSE_FORMAT_JSON_SCHEMA,
            'response_schema_id' => $promptSchema ?? PromptSchema::factory()->withJsonSchema()->create(),
        ]);
    }
}
