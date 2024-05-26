<?php

namespace Database\Factories\Workflow;

use App\Models\Team\Team;
use App\Models\Workflow\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id'     => team() ?? Team::factory(),
            'name'        => fake()->unique()->name,
            'description' => fake()->sentence,
        ];
    }
}
