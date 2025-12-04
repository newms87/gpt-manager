<?php

namespace Tests\Feature;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowNodeClipboardTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_clipboard_export_requires_authentication(): void
    {
        // Arrange
        auth()->logout();

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [1, 2, 3],
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_clipboard_export_validates_node_ids_required(): void
    {
        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            // Missing node_ids
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node_ids']);
    }

    public function test_clipboard_export_validates_node_ids_is_array(): void
    {
        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => 'not-an-array',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node_ids']);
    }

    public function test_clipboard_export_validates_node_ids_has_at_least_one_item(): void
    {
        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [], // Empty array
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node_ids']);
    }

    public function test_clipboard_export_validates_node_ids_are_integers(): void
    {
        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => ['not', 'integers'],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node_ids.0', 'node_ids.1']);
    }

    public function test_clipboard_export_returns_error_when_no_nodes_found(): void
    {
        // Act - Request nodes that don't exist
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [999, 998, 997],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No nodes found with provided IDs',
        ]);
    }

    public function test_clipboard_export_requires_all_nodes_from_same_workflow(): void
    {
        // Arrange - Create nodes in different workflows
        $workflow1 = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflow2 = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $taskDef1 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef2 = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow1->id,
            'task_definition_id'     => $taskDef1->id,
        ]);
        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow2->id, // Different workflow
            'task_definition_id'     => $taskDef2->id,
        ]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [$node1->id, $node2->id],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'All nodes must belong to the same workflow',
        ]);
    }

    public function test_clipboard_export_denies_access_to_other_teams_workflows(): void
    {
        // Arrange - Create workflow for a different team
        $otherTeamWorkflow = WorkflowDefinition::factory()->create(); // Different team
        $taskDef           = TaskDefinition::factory()->create();

        $node = WorkflowNode::factory()->create([
            'workflow_definition_id' => $otherTeamWorkflow->id,
            'task_definition_id'     => $taskDef->id,
        ]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [$node->id],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'You do not have access to this workflow',
        ]);
    }

    public function test_clipboard_export_returns_correct_json_structure(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agent    = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDef  = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $node = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDef->id,
            'name'                   => 'Test Node',
            'settings'               => ['x' => 100, 'y' => 200],
        ]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [$node->id],
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'type',
            'version',
            'nodes' => [
                '*' => [
                    'export_key',
                    'name',
                    'settings',
                    'params',
                    'task_definition_ref',
                ],
            ],
            'connections',
            'definitions',
        ]);

        $json = $response->json();
        $this->assertEquals('workflow-node-clipboard', $json['type']);
        $this->assertEquals('1.0', $json['version']);
        $this->assertCount(1, $json['nodes']);
        $this->assertEquals('Test Node', $json['nodes'][0]['name']);
        $this->assertArrayHasKey(TaskDefinition::class, $json['definitions']);
        $this->assertArrayHasKey(Agent::class, $json['definitions']);
    }

    public function test_clipboard_export_includes_connections_between_nodes(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
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
        ]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-export', [
            'node_ids' => [$node1->id, $node2->id],
        ]);

        // Assert
        $response->assertStatus(200);
        $json = $response->json();
        $this->assertCount(1, $json['connections']);
        $this->assertEquals('Test Connection', $json['connections'][0]['name']);
    }

    public function test_clipboard_import_requires_authentication(): void
    {
        // Arrange
        auth()->logout();

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => 1,
            'clipboard_data'         => [],
            'paste_position'         => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_clipboard_import_validates_workflow_definition_id_required(): void
    {
        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'clipboard_data' => [],
            'paste_position' => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workflow_definition_id']);
    }

    public function test_clipboard_import_validates_clipboard_data_required(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'paste_position'         => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['clipboard_data']);
    }

    public function test_clipboard_import_validates_paste_position_required(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'clipboard_data'         => [],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['paste_position']);
    }

    public function test_clipboard_import_validates_paste_position_coordinates(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'clipboard_data'         => [],
            'paste_position'         => ['x' => 'not-numeric', 'y' => 'not-numeric'],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['paste_position.x', 'paste_position.y']);
    }

    public function test_clipboard_import_returns_error_when_workflow_not_found(): void
    {
        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => 999,
            'clipboard_data'         => [
                'type'        => 'workflow-node-clipboard',
                'version'     => '1.0',
                'nodes'       => [],
                'connections' => [],
                'definitions' => [],
            ],
            'paste_position' => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Workflow not found',
        ]);
    }

    public function test_clipboard_import_denies_access_to_other_teams_workflow(): void
    {
        // Arrange - Create workflow for a different team
        $otherTeamWorkflow = WorkflowDefinition::factory()->create(); // Different team

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $otherTeamWorkflow->id,
            'clipboard_data'         => [
                'type'        => 'workflow-node-clipboard',
                'version'     => '1.0',
                'nodes'       => [],
                'connections' => [],
                'definitions' => [],
            ],
            'paste_position' => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'You do not have permission to edit this workflow',
        ]);
    }

    public function test_clipboard_import_validates_clipboard_data_type(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $invalidClipboardData = [
            'type'        => 'invalid-type',
            'version'     => '1.0',
            'nodes'       => [],
            'connections' => [],
            'definitions' => [],
        ];

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'clipboard_data'         => $invalidClipboardData,
            'paste_position'         => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Invalid clipboard data: not a workflow node clipboard',
        ]);
    }

    public function test_clipboard_import_creates_nodes_at_correct_position(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $clipboardData = [
            'type'    => 'workflow-node-clipboard',
            'version' => '1.0',
            'nodes'   => [
                [
                    'export_key'          => 'node_0',
                    'name'                => 'Imported Node',
                    'settings'            => ['x' => 100, 'y' => 200],
                    'params'              => ['test' => 'value'],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:999',
                ],
            ],
            'connections' => [],
            'definitions' => [
                TaskDefinition::class => [
                    999 => [
                        'name'                   => 'Imported Task',
                        'description'            => 'Test',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'task_runner_config'     => null,
                        'prompt'                 => 'Test prompt',
                        'input_artifact_mode'    => '',
                        'input_artifact_levels'  => [],
                        'output_artifact_mode'   => '',
                        'output_artifact_levels' => [],
                        'response_format'        => 'text',
                    ],
                ],
            ],
        ];

        // Paste at position (500, 600)
        // Original centroid is at (100, 200)
        // Offset should be (400, 400)
        // New position should be (100 + 400, 200 + 400) = (500, 600)

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'clipboard_data'         => $clipboardData,
            'paste_position'         => ['x' => 500, 'y' => 600],
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify node was created at correct position
        $node = WorkflowNode::where('workflow_definition_id', $workflow->id)
            ->where('name', 'Imported Node')
            ->first();

        $this->assertNotNull($node);
        $this->assertEquals(500, $node->settings['x']);
        $this->assertEquals(600, $node->settings['y']);
        $this->assertEquals(['test' => 'value'], $node->params);
    }

    public function test_clipboard_import_creates_multiple_nodes_and_connections(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $clipboardData = [
            'type'    => 'workflow-node-clipboard',
            'version' => '1.0',
            'nodes'   => [
                [
                    'export_key'          => 'node_0',
                    'name'                => 'Source Node',
                    'settings'            => ['x' => 100, 'y' => 200],
                    'params'              => [],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:999',
                ],
                [
                    'export_key'          => 'node_1',
                    'name'                => 'Target Node',
                    'settings'            => ['x' => 300, 'y' => 400],
                    'params'              => [],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:998',
                ],
            ],
            'connections' => [
                [
                    'source_export_key'  => 'node_0',
                    'target_export_key'  => 'node_1',
                    'name'               => 'Imported Connection',
                    'source_output_port' => 'output1',
                    'target_input_port'  => 'input1',
                ],
            ],
            'definitions' => [
                TaskDefinition::class => [
                    999 => [
                        'name'                   => 'Task 1',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'prompt'                 => 'Test',
                        'input_artifact_mode'    => '',
                        'input_artifact_levels'  => [],
                        'output_artifact_mode'   => '',
                        'output_artifact_levels' => [],
                        'response_format'        => 'text',
                    ],
                    998 => [
                        'name'                   => 'Task 2',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'prompt'                 => 'Test',
                        'input_artifact_mode'    => '',
                        'input_artifact_levels'  => [],
                        'output_artifact_mode'   => '',
                        'output_artifact_levels' => [],
                        'response_format'        => 'text',
                    ],
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'clipboard_data'         => $clipboardData,
            'paste_position'         => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'nodes',
        ]);

        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertCount(2, $json['nodes']);

        // Verify nodes were created
        $sourceNode = WorkflowNode::where('workflow_definition_id', $workflow->id)
            ->where('name', 'Source Node')
            ->first();
        $targetNode = WorkflowNode::where('workflow_definition_id', $workflow->id)
            ->where('name', 'Target Node')
            ->first();

        $this->assertNotNull($sourceNode);
        $this->assertNotNull($targetNode);

        // Verify connection was created
        $connection = WorkflowConnection::where('workflow_definition_id', $workflow->id)
            ->where('source_node_id', $sourceNode->id)
            ->where('target_node_id', $targetNode->id)
            ->first();

        $this->assertNotNull($connection);
        $this->assertEquals('Imported Connection', $connection->name);
        $this->assertEquals('output1', $connection->source_output_port);
        $this->assertEquals('input1', $connection->target_input_port);
    }

    public function test_clipboard_import_returns_created_nodes_with_relationships(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $clipboardData = [
            'type'    => 'workflow-node-clipboard',
            'version' => '1.0',
            'nodes'   => [
                [
                    'export_key'          => 'node_0',
                    'name'                => 'Test Node',
                    'settings'            => ['x' => 100, 'y' => 200],
                    'params'              => [],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:999',
                ],
            ],
            'connections' => [],
            'definitions' => [
                TaskDefinition::class => [
                    999 => [
                        'name'                   => 'Test Task',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'prompt'                 => 'Test',
                        'input_artifact_mode'    => '',
                        'input_artifact_levels'  => [],
                        'output_artifact_mode'   => '',
                        'output_artifact_levels' => [],
                        'response_format'        => 'text',
                    ],
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/workflow-nodes/clipboard-import', [
            'workflow_definition_id' => $workflow->id,
            'clipboard_data'         => $clipboardData,
            'paste_position'         => ['x' => 0, 'y' => 0],
        ]);

        // Assert
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('nodes', $json);
        $this->assertCount(1, $json['nodes']);

        $nodeData = $json['nodes'][0];
        $this->assertArrayHasKey('task_definition', $nodeData);
        $this->assertEquals('Test Node', $nodeData['name']);
        $this->assertEquals('Test Task', $nodeData['task_definition']['name']);
    }
}
