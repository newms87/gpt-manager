<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskProcessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_run_id'   => TaskRun::factory(),
            'thread_id'     => null,
            'started_at'    => null,
            'stopped_at'    => null,
            'failed_at'     => null,
            'completed_at'  => null,
            'timeout_at'    => null,
            'input_tokens'  => 0,
            'output_tokens' => 0,
        ];
    }
}
