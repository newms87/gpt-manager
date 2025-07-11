<?php

namespace Tests\Feature\McpServer;

use App\Api\Options\ResponsesApiOptions;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Agent\McpServer;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\McpServerConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentThreadMcpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team;
    protected Agent $agent;
    protected AgentThreadService $service;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->user->teams()->attach($this->team);
        
        $this->actingAs($this->user);
        session(['team_id' => $this->team->id]);
        
        $this->agent = Agent::factory()->create(['team_id' => $this->team->id]);
        $this->service = app(AgentThreadService::class);
    }

    public function test_responses_api_options_can_set_mcp_servers()
    {
        $mcpServers = [
            [
                'type' => 'mcp',
                'server_url' => 'https://api.example.com/mcp',
                'server_label' => 'test-server',
                'allowed_tools' => ['search', 'create'],
                'require_approval' => 'never',
                'headers' => ['Authorization' => 'Bearer token'],
            ]
        ];

        $options = new ResponsesApiOptions();
        $options->setMcpServers($mcpServers);

        $this->assertEquals($mcpServers, $options->getMcpServers());
    }

    public function test_responses_api_options_includes_mcp_servers_in_array()
    {
        $mcpServers = [
            [
                'type' => 'mcp',
                'server_url' => 'https://api.example.com/mcp',
                'server_label' => 'test-server',
            ]
        ];

        $options = new ResponsesApiOptions();
        $options->setMcpServers($mcpServers);

        $array = $options->toArray();

        $this->assertArrayHasKey('tools', $array);
        $this->assertEquals($mcpServers, $array['tools']);
    }

    public function test_responses_api_options_from_array_with_mcp_servers()
    {
        $data = [
            'temperature' => 0.7,
            'mcp_servers' => [
                [
                    'type' => 'mcp',
                    'server_url' => 'https://api.example.com/mcp',
                    'server_label' => 'test-server',
                ]
            ]
        ];

        $options = ResponsesApiOptions::fromArray($data);

        $this->assertEquals($data['mcp_servers'], $options->getMcpServers());
        $this->assertEquals(0.7, $options->getTemperature());
    }

    public function test_agent_thread_service_includes_mcp_configuration()
    {
        // Create MCP servers
        $mcpServer1 = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'label' => 'server-1',
            'server_url' => 'https://api1.example.com/mcp',
        ]);

        $mcpServer2 = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'label' => 'server-2',
            'server_url' => 'https://api2.example.com/mcp',
        ]);

        // Create task definition with MCP server configuration
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
            'task_runner_config' => [
                'mcp_server_ids' => [$mcpServer1->id, $mcpServer2->id],
            ],
        ]);

        // Create task run and process
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $agentThread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'agent_thread_id' => $agentThread->id,
        ]);

        // Create agent thread run
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        // Mock the task run relationship
        $agentThreadRun->taskRun = $taskRun;

        // Test that MCP configuration is retrieved
        $mcpConfigService = app(McpServerConfigurationService::class);
        $mcpConfig = $mcpConfigService->getMcpServerToolsForTaskDefinition($taskDefinition);

        $this->assertCount(2, $mcpConfig);
        $this->assertEquals('mcp', $mcpConfig[0]['type']);
        $this->assertEquals('server-1', $mcpConfig[0]['server_label']);
        $this->assertEquals('server-2', $mcpConfig[1]['server_label']);
    }

    public function test_mcp_configuration_service_handles_missing_task_definition()
    {
        $agentThread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
        ]);

        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        $mcpConfigService = app(McpServerConfigurationService::class);
        
        // This should not throw an exception and should return empty array
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => null,
        ]);

        $result = $mcpConfigService->getMcpServerToolsForTaskDefinition($taskDefinition);
        $this->assertEquals([], $result);
    }

    public function test_mcp_servers_are_formatted_correctly_for_openai_api()
    {
        $mcpServer = McpServer::factory()->active()->create([
            'team_id' => $this->team->id,
            'name' => 'Test MCP Server',
            'label' => 'test-mcp-server',
            'server_url' => 'https://api.example.com/mcp',
            'headers' => [
                'Authorization' => 'Bearer test-token',
                'X-Custom-Header' => 'custom-value',
            ],
            'allowed_tools' => ['search_products', 'create_order'],
            'require_approval' => 'never',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'task_runner_config' => ['mcp_server_ids' => [$mcpServer->id]],
        ]);

        $mcpConfigService = app(McpServerConfigurationService::class);
        $result = $mcpConfigService->getMcpServerToolsForTaskDefinition($taskDefinition);

        $expectedFormat = [
            'type' => 'mcp',
            'server_url' => 'https://api.example.com/mcp',
            'server_label' => 'test-mcp-server',
            'allowed_tools' => ['search_products', 'create_order'],
            'require_approval' => 'never',
            'headers' => [
                'Authorization' => 'Bearer test-token',
                'X-Custom-Header' => 'custom-value',
            ],
        ];

        $this->assertEquals([$expectedFormat], $result);
    }
}