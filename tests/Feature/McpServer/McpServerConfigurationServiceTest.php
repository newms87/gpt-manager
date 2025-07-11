<?php

namespace Tests\Feature\McpServer;

use App\Models\Agent\McpServer;
use App\Models\Task\TaskDefinition;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\AgentThread\McpServerConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team;
    protected McpServerConfigurationService $service;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->user->teams()->attach($this->team);
        
        $this->actingAs($this->user);
        session(['team_id' => $this->team->id]);
        
        $this->service = app(McpServerConfigurationService::class);
    }

    public function test_returns_empty_array_when_no_mcp_server_ids_configured()
    {
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => [],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertEquals([], $result);
    }

    public function test_returns_empty_array_when_mcp_server_ids_empty()
    {
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => ['mcp_server_ids' => []],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertEquals([], $result);
    }

    public function test_returns_mcp_server_tools_for_valid_active_servers()
    {
        $mcpServer1 = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'name' => 'Server 1',
            'label' => 'server-1',
            'server_url' => 'https://api1.example.com/mcp',
            'headers' => ['Authorization' => 'Bearer token1'],
            'allowed_tools' => ['search', 'create'],
            'require_approval' => 'never',
        ]);

        $mcpServer2 = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'name' => 'Server 2',
            'label' => 'server-2',
            'server_url' => 'https://api2.example.com/mcp',
            'headers' => ['Authorization' => 'Bearer token2'],
            'allowed_tools' => ['analyze', 'transform'],
            'require_approval' => 'always',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => [
                'mcp_server_ids' => [$mcpServer1->id, $mcpServer2->id],
            ],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertCount(2, $result);
        
        $this->assertEquals([
            [
                'type' => 'mcp',
                'server_url' => 'https://api1.example.com/mcp',
                'server_label' => 'server-1',
                'allowed_tools' => ['search', 'create'],
                'require_approval' => 'never',
                'headers' => ['Authorization' => 'Bearer token1'],
            ],
            [
                'type' => 'mcp',
                'server_url' => 'https://api2.example.com/mcp',
                'server_label' => 'server-2',
                'allowed_tools' => ['analyze', 'transform'],
                'require_approval' => 'always',
                'headers' => ['Authorization' => 'Bearer token2'],
            ],
        ], $result);
    }

    public function test_excludes_inactive_mcp_servers()
    {
        $activeServer = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'label' => 'active-server',
        ]);

        $inactiveServer = McpServer::factory()->inactive()->create([
            'team_id' => $this->team->id,
            'label' => 'inactive-server',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => [
                'mcp_server_ids' => [$activeServer->id, $inactiveServer->id],
            ],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertCount(1, $result);
        $this->assertEquals('active-server', $result[0]['server_label']);
    }

    public function test_excludes_servers_from_other_teams()
    {
        $otherTeam = Team::factory()->create();
        
        $ownServer = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'label' => 'own-server',
        ]);

        $otherTeamServer = McpServer::factory()->active()->create([
            'team_id' => $otherTeam->id,
            'label' => 'other-team-server',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => [
                'mcp_server_ids' => [$ownServer->id, $otherTeamServer->id],
            ],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertCount(1, $result);
        $this->assertEquals('own-server', $result[0]['server_label']);
    }

    public function test_handles_non_existent_mcp_server_ids()
    {
        $existingServer = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'label' => 'existing-server',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => [
                'mcp_server_ids' => [$existingServer->id, 999999], // Non-existent ID
            ],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertCount(1, $result);
        $this->assertEquals('existing-server', $result[0]['server_label']);
    }

    public function test_handles_null_headers_and_tools()
    {
        $mcpServer = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'headers' => null,
            'allowed_tools' => null,
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => ['mcp_server_ids' => [$mcpServer->id]],
        ]);

        $result = $this->service->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['headers']);
        $this->assertNull($result[0]['allowed_tools']);
    }
}