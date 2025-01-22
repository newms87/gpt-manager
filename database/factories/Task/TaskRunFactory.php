<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_definition_id' => TaskDefinition::factory(),
            'started_at'         => null,
            'stopped_at'         => null,
            'failed_at'          => null,
            'completed_at'       => null,
            'timeout_at'         => null,
            'input_tokens'       => 0,
            'output_tokens'      => 0,
        ];
    }
}
