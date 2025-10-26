<?php

namespace Database\Factories\Demand;

use App\Models\Demand\DemandTemplate;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Demand\DemandTemplate>
 */
class DemandTemplateFactory extends Factory
{
    protected $model = DemandTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id'        => Team::factory(),
            'user_id'        => User::factory(),
            'stored_file_id' => null,
            'name'           => $this->faker->words(3, true),
            'description'    => $this->faker->optional()->paragraph(),
            'category'       => $this->faker->optional()->randomElement(['Legal', 'Insurance', 'Medical', 'Business', 'Personal']),
            'metadata'       => $this->faker->optional()->passthrough([
                'tags'       => $this->faker->words(3),
                'created_by' => $this->faker->name(),
            ]),
            'is_active'      => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Indicate that the template is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the template category.
     */
    public function category(string $category): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Set the template to be for a specific team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn(array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Set the template to be created by a specific user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set the template to use a specific stored file.
     */
    public function withStoredFile(StoredFile $storedFile): static
    {
        return $this->state(fn(array $attributes) => [
            'stored_file_id' => $storedFile->id,
        ]);
    }

    /**
     * Set the template to use a Google Docs URL.
     */
    public function withGoogleDocsUrl(?string $url = null): static
    {
        $googleDocUrl = $url ?? 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit';

        return $this->state(function (array $attributes) use ($googleDocUrl) {
            $storedFile = StoredFile::factory()->create([
                'team_id' => $attributes['team_id'] ?? Team::factory(),
                'url'     => $googleDocUrl,
                'disk'    => 'external',
                'mime'    => 'application/vnd.google-apps.document',
                'meta'    => [
                    'type' => 'google_docs_template',
                ],
            ]);

            return [
                'stored_file_id' => $storedFile->id,
            ];
        });
    }
}
