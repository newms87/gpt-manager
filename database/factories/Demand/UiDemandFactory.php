<?php

namespace Database\Factories\Demand;

use App\Models\Demand\UiDemand;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UiDemandFactory extends Factory
{
    protected $model = UiDemand::class;

    public function definition(): array
    {
        return [
            'team_id'     => Team::factory(),
            'user_id'     => User::factory(),
            'title'       => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status'      => UiDemand::STATUS_DRAFT,
        ];
    }
}
