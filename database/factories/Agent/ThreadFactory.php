<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thread>
 */
class ThreadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'  => Team::factory(),
            'user_id'  => fn(array $attributes) => User::factory()->recycle(Team::find($attributes['team_id']))->create(),
            'agent_id' => fn(array $attributes) => Agent::factory()->recycle(Team::find($attributes['team_id']))->create(),
            'name'     => fake()->words(3, true),
            'summary'  => '',
        ];
    }

    public function withMessage(Message $message)
    {
        return $this->afterCreating(fn(Thread $thread) => $thread->messages()->save($message));
    }

    public function withMessages($count = 3): ThreadFactory|Factory
    {
        return $this->afterCreating(fn(Thread $thread) => $thread->messages()->saveMany(
            Message::factory()->count($count)->make(['thread_id' => $thread])
        ));
    }
}
