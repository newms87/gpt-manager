<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'role'      => fake()->randomElement([Message::ROLE_USER, Message::ROLE_ASSISTANT]),
            'title'     => fake()->sentence,
            'summary'   => '',
            'content'   => fake()->paragraphs(3, true),
        ];
    }
}
