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
            'user_id'  => fn(array $attributes) => User::factory()->create(['team_id' => $attributes['team_id']]),
            'agent_id' => fn(array $attributes) => Agent::factory()->create(['team_id' => $attributes['team_id']]),
            'name'     => fake()->words(3, true),
            'summary'  => fake()->paragraph,
        ];
    }

    public function configure(): ThreadFactory|Factory
    {
        return $this->afterCreating(function (Thread $thread) {
            $thread->messages()->saveMany(Message::factory()->count(3)->make(['thread_id' => $thread]));
        });
    }
}
