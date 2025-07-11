<?php

namespace Tests\Unit\McpServer;

use App\Models\Agent\McpServer;
use App\Models\Team\Team;
use App\Models\User;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    public function test_mcp_server_creation()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $this->actingAs($user);

        $mcpServer = McpServer::factory()->create([
            'team_id' => $team->id,
            'name' => 'Test MCP Server',
            'description' => 'A test MCP server',
            'server_url' => 'http://localhost:8080',
            'headers' => ['Authorization' => 'Bearer token'],
            'allowed_tools' => ['tool1', 'tool2'],
        ]);

        $this->assertInstanceOf(McpServer::class, $mcpServer);
        $this->assertEquals('Test MCP Server', $mcpServer->name);
        $this->assertEquals('A test MCP server', $mcpServer->description);
        $this->assertEquals('http://localhost:8080', $mcpServer->server_url);
        $this->assertIsArray($mcpServer->headers);
        $this->assertIsArray($mcpServer->allowed_tools);
    }

    public function test_mcp_server_validation_passes_with_valid_data()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $this->actingAs($user);

        $mcpServer = new McpServer([
            'team_id' => $team->id,
            'name' => 'Valid MCP Server',
            'server_url' => 'https://api.example.com/mcp',
            'headers' => ['Content-Type' => 'application/json'],
            'allowed_tools' => ['read', 'write'],
        ]);

        $this->assertInstanceOf(McpServer::class, $mcpServer->validate());
    }

    public function test_mcp_server_validation_fails_with_missing_name()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $this->actingAs($user);

        $mcpServer = new McpServer([
            'team_id' => $team->id,
            'server_url' => 'https://api.example.com/mcp',
        ]);

        $mcpServer->validate();
    }

    public function test_mcp_server_validation_fails_with_invalid_url()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $this->actingAs($user);

        $mcpServer = new McpServer([
            'team_id' => $team->id,
            'name' => 'test-server',
            'server_url' => 'not-a-valid-url',
        ]);

        $mcpServer->validate();
    }
}