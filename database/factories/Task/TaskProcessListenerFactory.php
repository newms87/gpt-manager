<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskProcessListenerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_process_id'  => TaskProcess::factory(),
            'event_type'       => fake()->randomElement(TaskProcessListener::$allowedEventTypes),
            'event_id'         => fn($data) => $data['event_type']::factory(),
            'event_fired_at'   => null,
            'event_handled_at' => null,
        ];
    }
}
