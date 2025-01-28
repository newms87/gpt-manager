<?php

namespace Database\Factories\Agent;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentThreadMessage>
 */
class AgentThreadMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agent_thread_id' => AgentThread::factory(),
            'role'            => fake()->randomElement([AgentThreadMessage::ROLE_USER, AgentThreadMessage::ROLE_ASSISTANT]),
            'title'           => fake()->sentence,
            'summary'         => '',
            'content'         => fake()->paragraphs(3, true),
        ];
    }
}
