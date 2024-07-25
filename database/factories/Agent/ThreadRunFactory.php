<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent\ThreadRun>
 */
class ThreadRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id'       => Thread::factory(),
            'status'          => ThreadRun::STATUS_RUNNING,
            'temperature'     => 1,
            'tool_choice'     => 'auto',
            'response_format' => 'text',
            'started_at'      => now(),
            'completed_at'    => null,
            'failed_at'       => null,
            'refreshed_at'    => null,
            'input_tokens'    => 0,
            'output_tokens'   => 0,
        ];
    }
}
