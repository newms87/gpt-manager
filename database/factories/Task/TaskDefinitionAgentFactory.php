<?php

namespace Database\Factories\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionAgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_definition_id'        => TaskDefinition::factory(),
            'agent_id'                  => Agent::factory(),
            'include_text'              => false,
            'include_files'             => false,
            'include_data'              => false,
            'input_schema_id'           => null,
            'input_schema_fragment_id'  => null,
            'output_schema_id'          => null,
            'output_schema_fragment_id' => null,
        ];
    }

    public function withOutputSchema($schema, $fragmentSelector = []): static
    {
        return $this->afterCreating(function (TaskDefinitionAgent $taskDefinitionAgent) use ($schema, $fragmentSelector) {
            SchemaAssociation::factory()->withSchema($schema, $fragmentSelector)->create([
                'object_type' => TaskDefinitionAgent::class,
                'object_id'   => $taskDefinitionAgent->id,
            ]);
        });
    }
}
