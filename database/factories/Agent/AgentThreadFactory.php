<?php

namespace Database\Factories\Agent;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentThread>
 */
class AgentThreadFactory extends Factory
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

    public function withMessage(AgentThreadMessage $message)
    {
        return $this->afterCreating(fn(AgentThread $thread) => $thread->messages()->save($message));
    }

    public function withMessages($count = 3): AgentThreadFactory|Factory
    {
        return $this->afterCreating(fn(AgentThread $thread) => $thread->messages()->saveMany(
            AgentThreadMessage::factory()->count($count)->make(['agent_thread_id' => $thread])
        ));
    }
}
