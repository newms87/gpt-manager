<?php

namespace Tests\Feature\McpServer;

use App\Models\Agent\McpServer;
use App\Models\Team\Team;
use App\Models\User;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    public function test_creates_mcp_server_via_api()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $mcpServerData = [
            'name'        => 'Test MCP Server',
            'description' => 'A test MCP server for API testing',
            'server_url'  => 'http://localhost:8080',
            'headers'     => [
                'Authorization' => 'Bearer test-token',
                'Content-Type'  => 'application/json'
            ],
            'allowed_tools' => ['tool1', 'tool2'],
        ];

        $response = $this->postJson(route('mcp-servers.apply-action.create'), [
            'action' => 'create',
            'data' => $mcpServerData
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mcp_servers', [
            'name'       => 'Test MCP Server',
            'server_url' => 'http://localhost:8080',
            'team_id'    => $team->id,
        ]);
    }

    public function test_validates_required_fields()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $response = $this->postJson(route('mcp-servers.apply-action.create'), [
            'action' => 'create',
            'data' => []
        ]);

        $response->assertStatus(400);
    }

    public function test_reads_mcp_server_via_api()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $mcpServer = McpServer::factory()->create([
            'team_id'    => $team->id,
            'name'       => 'Test Server',
            'server_url' => 'http://example.com',
        ]);

        $response = $this->getJson(route('mcp-servers.details', $mcpServer));

        $response->assertStatus(200)
            ->assertJson([
                'id'         => $mcpServer->id,
                'name'       => 'Test Server',
                'server_url' => 'http://example.com',
            ]);
    }

    public function test_updates_mcp_server_via_api()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $mcpServer = McpServer::factory()->create([
            'team_id' => $team->id,
            'name'    => 'Original Server',
        ]);

        $updateData = [
            'name'        => 'Updated Server',
            'description' => 'Updated description',
            'server_url'  => 'http://updated.example.com',
        ];

        $response = $this->postJson(route('mcp-servers.apply-action', $mcpServer), [
            'action' => 'update',
            'data' => $updateData
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mcp_servers', [
            'id'         => $mcpServer->id,
            'name'       => 'Updated Server',
            'server_url' => 'http://updated.example.com',
        ]);
    }

    public function test_deletes_mcp_server_via_api()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $mcpServer = McpServer::factory()->create([
            'team_id' => $team->id,
        ]);

        $response = $this->postJson(route('mcp-servers.apply-action', $mcpServer), [
            'action' => 'delete'
        ]);

        $response->assertStatus(200);
        $this->assertSoftDeleted($mcpServer);
    }

    public function test_duplicates_mcp_server()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $originalServer = McpServer::factory()->create([
            'team_id'     => $team->id,
            'name'        => 'original-server',
            'description' => 'Original description',
            'server_url'  => 'http://original.example.com',
        ]);

        $response = $this->postJson(route('mcp-servers.apply-action', $originalServer), [
            'action' => 'copy'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mcp_servers', [
            'name'        => 'original-server (1)',
            'description' => 'Original description',
            'server_url'  => 'http://original.example.com',
            'team_id'     => $team->id,
        ]);

        $duplicatedServer = McpServer::where('name', 'original-server (1)')->first();
        $this->assertNotEquals($originalServer->id, $duplicatedServer->id);
    }

    public function test_validates_server_url_format()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $response = $this->postJson(route('mcp-servers.apply-action.create'), [
            'action' => 'create',
            'data' => [
                'name'       => 'test-server',
                'server_url' => 'invalid-url',
            ]
        ]);

        $response->assertStatus(400);
    }

    public function test_validates_name_is_required()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $response = $this->postJson(route('mcp-servers.apply-action.create'), [
            'action' => 'create',
            'data' => [
                'server_url' => 'http://example.com',
            ]
        ]);

        $response->assertStatus(400);
    }

    public function test_validates_server_url_is_required()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($user);
        $user->currentTeam = $team;
        $this->actingAs($user);

        $response = $this->postJson(route('mcp-servers.apply-action.create'), [
            'action' => 'create',
            'data' => [
                'name' => 'test-server',
            ]
        ]);

        $response->assertStatus(400);
    }

    public function test_scopes_to_team()
    {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();

        $server1 = McpServer::factory()->create(['team_id' => $team1->id]);
        McpServer::factory()->create(['team_id' => $team2->id]);

        $user1 = User::factory()->create();
        $team1->users()->attach($user1);
        $user1->currentTeam = $team1;
        $this->actingAs($user1);

        $response = $this->postJson(route('mcp-servers.list'), []);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($server1->id, $response->json('data.0.id'));
    }
}