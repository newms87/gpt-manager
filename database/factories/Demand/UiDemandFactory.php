<?php

namespace Database\Factories\Demand;

use App\Models\Demand\UiDemand;
use Illuminate\Database\Eloquent\Factories\Factory;

class UiDemandFactory extends Factory
{
    protected $model = UiDemand::class;

    public function definition(): array
    {
        return [
            'team_id'     => \App\Models\Team\Team::factory(),
            'user_id'     => \App\Models\User::factory(),
            'title'       => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status'      => UiDemand::STATUS_DRAFT,
        ];
    }
}
