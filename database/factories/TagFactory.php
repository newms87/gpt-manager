<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name'    => fake()->unique()->word,
            'type'    => null,
        ];
    }

    public function withType(string $type): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type,
        ]);
    }
}
