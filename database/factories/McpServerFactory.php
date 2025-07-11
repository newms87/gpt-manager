<?php

namespace Database\Factories;

use App\Models\Agent\McpServer;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent\McpServer>
 */
class McpServerFactory extends Factory
{
    protected $model = McpServer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->company . ' MCP Server',
            'label' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'server_url' => $this->faker->url(),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->faker->lexify('????????'),
                'Content-Type' => 'application/json',
            ],
            'allowed_tools' => $this->faker->randomElements(
                ['search', 'create', 'update', 'delete', 'analyze', 'transform'],
                $this->faker->numberBetween(1, 4)
            ),
            'require_approval' => $this->faker->randomElement(['never', 'always']),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Indicate that the MCP server is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the MCP server is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the MCP server requires approval.
     */
    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_approval' => 'always',
        ]);
    }

    /**
     * Indicate that the MCP server never requires approval.
     */
    public function neverRequiresApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_approval' => 'never',
        ]);
    }
}
