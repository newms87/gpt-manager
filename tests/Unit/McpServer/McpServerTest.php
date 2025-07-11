<?php

namespace Tests\Unit\McpServer;

use App\Models\Agent\McpServer;
use App\Models\Team\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_server_can_be_created()
    {
        $team = Team::factory()->create();
        
        $mcpServer = McpServer::factory()->create([
            'team_id' => $team->id,
            'name' => 'Test MCP Server',
            'label' => 'test-mcp-server',
            'server_url' => 'https://api.example.com/mcp',
        ]);

        $this->assertDatabaseHas('mcp_servers', [
            'name' => 'Test MCP Server',
            'label' => 'test-mcp-server',
            'team_id' => $team->id,
        ]);
    }

    public function test_mcp_server_factory_works()
    {
        $mcpServer = McpServer::factory()->create();

        $this->assertNotNull($mcpServer->id);
        $this->assertNotNull($mcpServer->team_id);
        $this->assertNotNull($mcpServer->name);
        $this->assertNotNull($mcpServer->label);
        $this->assertNotNull($mcpServer->server_url);
        $this->assertIsBool($mcpServer->is_active);
        $this->assertContains($mcpServer->require_approval, ['never', 'always']);
    }

    public function test_mcp_server_factory_states()
    {
        $activeServer = McpServer::factory()->active()->create();
        $this->assertTrue($activeServer->is_active);

        $inactiveServer = McpServer::factory()->inactive()->create();
        $this->assertFalse($inactiveServer->is_active);

        $approvalServer = McpServer::factory()->requiresApproval()->create();
        $this->assertEquals('always', $approvalServer->require_approval);

        $noApprovalServer = McpServer::factory()->neverRequiresApproval()->create();
        $this->assertEquals('never', $noApprovalServer->require_approval);
    }

    public function test_mcp_server_belongs_to_team()
    {
        $team = Team::factory()->create();
        $mcpServer = McpServer::factory()->create(['team_id' => $team->id]);

        $this->assertEquals($team->id, $mcpServer->team->id);
    }

    public function test_mcp_server_casts_attributes_correctly()
    {
        $headers = ['Authorization' => 'Bearer token'];
        $allowedTools = ['search', 'create'];

        $mcpServer = McpServer::factory()->create([
            'headers' => $headers,
            'allowed_tools' => $allowedTools,
            'is_active' => true,
        ]);

        $this->assertEquals($headers, $mcpServer->headers);
        $this->assertEquals($allowedTools, $mcpServer->allowed_tools);
        $this->assertTrue($mcpServer->is_active);
    }

    public function test_mcp_server_validation_passes_with_valid_data()
    {
        $team = Team::factory()->create();
        
        $mcpServer = new McpServer([
            'team_id' => $team->id,
            'name' => 'Test Server',
            'label' => 'test-server',
            'server_url' => 'https://api.example.com/mcp',
            'require_approval' => 'never',
        ]);

        // Should not throw exception
        $mcpServer->validate();
        $this->assertTrue(true);
    }

    public function test_mcp_server_validation_fails_with_invalid_url()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $team = Team::factory()->create();
        
        $mcpServer = new McpServer([
            'team_id' => $team->id,
            'name' => 'Test Server',
            'label' => 'test-server',
            'server_url' => 'not-a-valid-url',
            'require_approval' => 'never',
        ]);

        $mcpServer->validate();
    }

    public function test_mcp_server_validation_fails_with_invalid_require_approval()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $team = Team::factory()->create();
        
        $mcpServer = new McpServer([
            'team_id' => $team->id,
            'name' => 'Test Server',
            'label' => 'test-server',
            'server_url' => 'https://api.example.com/mcp',
            'require_approval' => 'invalid',
        ]);

        $mcpServer->validate();
    }

    public function test_mcp_server_string_representation()
    {
        $mcpServer = McpServer::factory()->create([
            'name' => 'Test Server with Very Long Name That Should Be Truncated',
            'server_url' => 'https://api.example.com/mcp',
        ]);

        $string = (string) $mcpServer;
        
        $this->assertStringContainsString('McpServer', $string);
        $this->assertStringContainsString($mcpServer->id, $string);
        $this->assertStringContainsString('https://api.example.com/mcp', $string);
    }

    public function test_mcp_server_soft_deletes()
    {
        $mcpServer = McpServer::factory()->create();
        $id = $mcpServer->id;

        $mcpServer->delete();

        $this->assertSoftDeleted('mcp_servers', ['id' => $id]);
        $this->assertNull(McpServer::find($id));
        $this->assertNotNull(McpServer::withTrashed()->find($id));
    }
}