<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskArtifactFilterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_task_definition_id' => TaskDefinition::factory(),
            'target_task_definition_id' => TaskDefinition::factory(),
            'include_text'              => true,
            'include_files'             => true,
            'include_json'              => true,
            'include_meta'              => true,
            'fragment_selector'         => null,
        ];
    }
}
