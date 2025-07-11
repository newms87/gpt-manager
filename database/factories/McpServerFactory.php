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
        ];
    }

}
