<?php

namespace Database\Factories\Task;

use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Team\Team;
use App\Services\Task\Runners\BaseTaskRunner;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'              => Team::factory(),
            'name'                 => fake()->unique()->name,
            'description'          => fake()->sentence,
            'task_runner_class'    => BaseTaskRunner::RUNNER_NAME,
            'task_runner_config'   => null,
            'artifact_split_mode'  => '',
            'schema_definition_id' => null,
            'response_format'      => AgentThreadRun::RESPONSE_FORMAT_TEXT,
        ];
    }

    public function withSchemaDefinition(SchemaDefinition $schemaDefinition = null): static
    {
        return $this->state([
            'schema_definition_id' => $schemaDefinition ? $schemaDefinition->id : SchemaDefinition::factory()->create()->id,
            'response_format'      => AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA,
        ]);
    }
}
