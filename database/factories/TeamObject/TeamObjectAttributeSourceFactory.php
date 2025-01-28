<?php

namespace Database\Factories\TeamObject;

use App\Models\TeamObject\TeamObjectAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamObjectAttributeSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'object_attribute_id'     => TeamObjectAttribute::factory(),
            'source_type'             => fake()->randomElement(['message', 'file', 'url']),
            'source_id'               => fake()->unique()->uuid,
            'explanation'             => fake()->sentence,
            'stored_file_id'          => null,
            'agent_thread_message_id' => null,
        ];
    }
}
