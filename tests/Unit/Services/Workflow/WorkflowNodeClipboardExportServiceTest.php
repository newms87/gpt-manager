<?php

namespace Tests\Unit\Services\Workflow;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\Workflow\WorkflowNodeClipboardExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowNodeClipboardExportServiceTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_exports_single_node_with_basic_structure(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $node = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef->id,
            'name'                   => 'Test Node',
            'settings'               => ['x' => 100, 'y' => 200],
            'params'                 => ['param1' => 'value1'],
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node]);

        // Assert
        $this->assertEquals('workflow-node-clipboard', $result['type']);
        $this->assertEquals('1.0', $result['version']);
        $this->assertCount(1, $result['nodes']);

        $exportedNode = $result['nodes'][0];
        $this->assertEquals('node_0', $exportedNode['export_key']);
        $this->assertEquals('Test Node', $exportedNode['name']);
        $this->assertEquals(['x' => 100, 'y' => 200], $exportedNode['settings']);
        $this->assertEquals(['param1' => 'value1'], $exportedNode['params']);
        $this->assertNotNull($exportedNode['task_definition_ref']);
        $this->assertStringContainsString('App\Models\Task\TaskDefinition:', $exportedNode['task_definition_ref']);
    }

    public function test_exports_multiple_nodes_with_sequential_export_keys(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef1 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef2 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef3 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef1->id,
            'name'                   => 'Node 1',
        ]);
        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef2->id,
            'name'                   => 'Node 2',
        ]);
        $node3 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef3->id,
            'name'                   => 'Node 3',
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node1, $node2, $node3]);

        // Assert
        $this->assertCount(3, $result['nodes']);
        $this->assertEquals('node_0', $result['nodes'][0]['export_key']);
        $this->assertEquals('node_1', $result['nodes'][1]['export_key']);
        $this->assertEquals('node_2', $result['nodes'][2]['export_key']);
        $this->assertEquals('Node 1', $result['nodes'][0]['name']);
        $this->assertEquals('Node 2', $result['nodes'][1]['name']);
        $this->assertEquals('Node 3', $result['nodes'][2]['name']);
    }

    public function test_includes_task_definition_in_definitions(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef = TaskDefinition::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Test Task Definition',
            'description' => 'Test Description',
        ]);
        $node = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef->id,
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node]);

        // Assert
        $this->assertArrayHasKey(TaskDefinition::class, $result['definitions']);
        $this->assertArrayHasKey($taskDef->id, $result['definitions'][TaskDefinition::class]);
        $taskDefData = $result['definitions'][TaskDefinition::class][$taskDef->id];
        $this->assertEquals('Test Task Definition', $taskDefData['name']);
        $this->assertEquals('Test Description', $taskDefData['description']);
    }

    public function test_includes_agent_when_task_definition_has_agent(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Agent',
            'model'   => 'test-model',
        ]);
        $taskDef = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $node = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef->id,
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node]);

        // Assert
        $this->assertArrayHasKey(Agent::class, $result['definitions']);
        $this->assertArrayHasKey($agent->id, $result['definitions'][Agent::class]);
        $agentData = $result['definitions'][Agent::class][$agent->id];
        $this->assertEquals('Test Agent', $agentData['name']);
        $this->assertEquals('test-model', $agentData['model']);
    }

    public function test_includes_schema_definition_when_task_definition_has_schema(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $schema = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Schema',
            'type'    => 'object',
        ]);
        $taskDef = TaskDefinition::factory()->withSchemaDefinition($schema)->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $node = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef->id,
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node]);

        // Assert
        $this->assertArrayHasKey(SchemaDefinition::class, $result['definitions']);
        $this->assertArrayHasKey($schema->id, $result['definitions'][SchemaDefinition::class]);
        $schemaData = $result['definitions'][SchemaDefinition::class][$schema->id];
        $this->assertEquals('Test Schema', $schemaData['name']);
        $this->assertEquals('object', $schemaData['type']);
    }

    public function test_exports_connections_between_selected_nodes(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef1 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef2 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef1->id,
        ]);
        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef2->id,
        ]);

        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'source_node_id'         => $node1->id,
            'target_node_id'         => $node2->id,
            'name'                   => 'Test Connection',
            'source_output_port'     => 'output1',
            'target_input_port'      => 'input1',
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node1, $node2]);

        // Assert
        $this->assertCount(1, $result['connections']);
        $connection = $result['connections'][0];
        $this->assertEquals('node_0', $connection['source_export_key']);
        $this->assertEquals('node_1', $connection['target_export_key']);
        $this->assertEquals('Test Connection', $connection['name']);
        $this->assertEquals('output1', $connection['source_output_port']);
        $this->assertEquals('input1', $connection['target_input_port']);
    }

    public function test_does_not_export_connections_to_unselected_nodes(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef1 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef2 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef3 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef1->id,
        ]);
        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef2->id,
        ]);
        $node3 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef3->id,
        ]);

        // Create connections: node1 -> node2 and node2 -> node3
        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'source_node_id'         => $node1->id,
            'target_node_id'         => $node2->id,
        ]);
        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'source_node_id'         => $node2->id,
            'target_node_id'         => $node3->id,
        ]);

        // Act - Export only node1 and node2 (not node3)
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node1, $node2]);

        // Assert - Only connection between node1 and node2 should be exported
        $this->assertCount(1, $result['connections']);
        $connection = $result['connections'][0];
        $this->assertEquals('node_0', $connection['source_export_key']);
        $this->assertEquals('node_1', $connection['target_export_key']);
    }

    public function test_exports_multiple_connections_correctly(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef1 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef2 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef3 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef1->id,
        ]);
        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef2->id,
        ]);
        $node3 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef3->id,
        ]);

        // Create connections: node1 -> node2 and node1 -> node3
        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'source_node_id'         => $node1->id,
            'target_node_id'         => $node2->id,
            'name'                   => 'Connection 1-2',
        ]);
        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'source_node_id'         => $node1->id,
            'target_node_id'         => $node3->id,
            'name'                   => 'Connection 1-3',
        ]);

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node1, $node2, $node3]);

        // Assert
        $this->assertCount(2, $result['connections']);
        $connectionNames = array_column($result['connections'], 'name');
        $this->assertContains('Connection 1-2', $connectionNames);
        $this->assertContains('Connection 1-3', $connectionNames);
    }

    public function test_empty_connections_when_no_connections_between_selected_nodes(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskDef1 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef2 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef1->id,
        ]);
        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef2->id,
        ]);

        // No connections created

        // Act
        $service = new WorkflowNodeClipboardExportService();
        $result  = $service->exportNodes([$node1, $node2]);

        // Assert
        $this->assertEmpty($result['connections']);
    }
}
