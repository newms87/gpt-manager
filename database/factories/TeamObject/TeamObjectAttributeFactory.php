<?php

namespace Database\Factories\TeamObject;

use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamObjectAttributeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_object_id'      => TeamObject::factory(),
            'name'                => fake()->unique()->name,
            'text_value'          => fake()->sentence,
            'json_value'          => null,
            'reason'              => fake()->sentence,
            'confidence'          => fake()->randomElement(['High', 'Medium', 'Low', '']),
            'agent_thread_run_id' => null,
        ];
    }
}
