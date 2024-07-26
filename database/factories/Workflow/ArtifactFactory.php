<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\Artifact;
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
            'name'    => fake()->word,
            'model'   => fake()->word,
            'content' => null,
            'data'    => null,
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

        return $this->afterCreating(fn(Artifact $artifact) => $artifact->storedFiles()->attach($storedFile));
    }
}
