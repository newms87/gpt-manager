<?php

namespace Database\Factories\Task;

use App\Models\Task\Artifact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => fake()->word,
            'model'        => fake()->word,
            'text_content' => null,
            'json_content' => null,
        ];
    }

    public function withStoredFiles($count = 2, $attributes = []): static
    {
        return $this->afterCreating(fn(Artifact $artifact) => $artifact->storedFiles()->attach(StoredFile::factory()->count($count)->create($attributes)));
    }

    public function withStoredFile(?StoredFile $storedFile = null): static
    {
        if (!$storedFile) {
            $storedFile = StoredFile::create([
                'disk'     => 'local',
                'filepath' => 'test.jpg',
                'filename' => 'test.jpg',
                'mime'     => 'image/jpeg',
            ]);
        }

        return $this->afterCreating(fn(Artifact $artifact) => $artifact->storedFiles()->attach($storedFile));
    }
}
