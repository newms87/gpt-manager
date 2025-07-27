<?php

namespace Database\Factories;

use App\Models\UiDemand;
use Illuminate\Database\Eloquent\Factories\Factory;

class UiDemandFactory extends Factory
{
    protected $model = UiDemand::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => UiDemand::STATUS_DRAFT,
        ];
    }
}
