<?php

namespace Database\Factories\Agent;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent\AgentThreadRun>
 */
class AgentThreadRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_thread_id' => AgentThread::factory(),
            'status'          => AgentThreadRun::STATUS_RUNNING,
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
