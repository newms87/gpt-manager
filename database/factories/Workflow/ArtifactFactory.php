<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Artifact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'    => fake()->word,
            'model'   => fake()->word,
            'content' => null,
            'data'    => null,
        ];
    }
}
