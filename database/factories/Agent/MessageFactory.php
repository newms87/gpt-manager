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
            'role'      => $this->faker->randomElement([Message::ROLE_USER, Message::ROLE_ASSISTANT]),
            'title'     => $this->faker->sentence,
            'summary'   => $this->faker->paragraph,
            'content'   => $this->faker->paragraphs(3, true),
        ];
    }
}
