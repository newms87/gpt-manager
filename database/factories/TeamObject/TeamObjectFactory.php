<?php

namespace Database\Factories\TeamObject;

use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamObject>
 */
class TeamObjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prompt_schema_id' => null,
            'root_object_id'   => null,
            'type'             => fake()->unique()->colorName(),
            'name'             => fake()->unique()->company,
            'date'             => null,
            'description'      => fake()->sentence(),
            'url'              => null,
            'meta'             => null,
        ];
    }
}
