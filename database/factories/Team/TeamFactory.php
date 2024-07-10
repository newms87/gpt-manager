<?php

namespace Database\Factories\Team;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->unique()->company,
            'namespace'   => '',
            'logo'        => null,
            'schema_file' => null,
        ];
    }
}
