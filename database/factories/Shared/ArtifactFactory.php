<?php

namespace Database\Factories\Shared;

use App\Models\Shared\Artifact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group'   => fake()->word,
            'name'    => fake()->word,
            'model'   => fake()->word,
            'content' => fake()->sentence(),
            'data'    => null,
        ];
    }
}
