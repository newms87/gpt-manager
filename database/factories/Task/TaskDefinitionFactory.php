<?php

namespace Database\Factories\Task;

use App\Services\Task\TaskServiceBase;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_service'           => TaskServiceBase::class,
            'input_grouping'         => null,
            'input_group_chunk_size' => 1,
        ];
    }
}
