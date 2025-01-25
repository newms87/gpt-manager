<?php

namespace Database\Factories\Task;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionAgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_definition_id'   => TaskDefinition::factory(),
            'agent_id'             => Agent::factory(),
            'include_text'         => false,
            'include_files'        => false,
            'include_data'         => false,
            'input_schema_id'      => null,
            'input_sub_selection'  => null,
            'output_schema_id'     => null,
            'output_sub_selection' => null,
        ];
    }
}
