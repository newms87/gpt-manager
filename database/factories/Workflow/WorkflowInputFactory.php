<?php

namespace Database\Factories\Workflow;

use App\Models\Team\Team;
use App\Models\User;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Factories\Factory;
use Newms87\Danx\Models\Utilities\StoredFile;

class WorkflowInputFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name'    => fake()->unique()->name,
            'content' => fake()->sentence,
            'data'    => [],
            'tokens'  => fake()->numberBetween(1, 1000),
        ];
    }

    public function withStoredFile(StoredFile $storedFile = null): static
    {
        if (!$storedFile) {
            $storedFile = StoredFile::create([
                'disk'     => 'local',
                'filepath' => 'test.jpg',
                'filename' => 'test.jpg',
                'mime'     => 'image/jpeg',
            ]);
        }

        return $this->afterCreating(fn(WorkflowInput $workflowInput) => $workflowInput->storedFiles()->attach($storedFile));
    }
}
