<?php

namespace Database\Factories\TeamObject;

use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamObjectRelationshipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'relationship_name' => fake()->word,
            'object_id'         => TeamObject::factory(),
            'related_object_id' => TeamObject::factory(),
        ];
    }
}
