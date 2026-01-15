<?php

namespace Tests\Unit\Services\Workflow;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\Workflow\WorkflowNodeClipboardImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowNodeClipboardImportServiceTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_validates_clipboard_type(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $service  = new WorkflowNodeClipboardImportService();

        $invalidClipboardData = [
            'type'        => 'invalid-type',
            'version'     => '1.0',
            'nodes'       => [],
            'connections' => [],
            'definitions' => [],
        ];

        // Act & Assert
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid clipboard data: not a workflow node clipboard');
        $service->importNodes($workflow, $invalidClipboardData, ['x' => 0, 'y' => 0]);
    }

    public function test_validates_nodes_are_present(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $service  = new WorkflowNodeClipboardImportService();

        $clipboardData = [
            'type'        => 'workflow-node-clipboard',
            'version'     => '1.0',
            'nodes'       => [], // Empty nodes
            'connections' => [],
            'definitions' => [],
        ];

        // Act & Assert
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No nodes to import');
        $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);
    }

    public function test_imports_creates_new_task_definition_when_no_match_exists(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $service  = new WorkflowNodeClipboardImportService();

        $clipboardData = [
            'type'    => 'workflow-node-clipboard',
            'version' => '1.0',
            'nodes'   => [
                [
                    'export_key'          => 'node_0',
                    'name'                => 'Test Node',
                    'settings'            => ['x' => 100, 'y' => 200],
                    'params'              => ['test' => 'value'],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:999',
                ],
            ],
            'connections' => [],
            'definitions' => [
                TaskDefinition::class => [
                    999 => [
                        'name'                   => 'New Task Definition',
                        'description'            => 'Test Description',
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

        // Act
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 500, 'y' => 300]);

        // Assert
        $this->assertCount(1, $createdNodes);
        $node = $createdNodes[0];
        $this->assertEquals($workflow->id, $node->workflow_definition_id);
        $this->assertEquals('Test Node', $node->name);

        // Verify TaskDefinition was created with original name
        $taskDef = TaskDefinition::where('team_id', $this->user->currentTeam->id)
            ->where('name', 'New Task Definition')
            ->first();
        $this->assertNotNull($taskDef);
        $this->assertEquals('Test Description', $taskDef->description);
        $this->assertEquals($taskDef->id, $node->task_definition_id);
    }

    public function test_imports_always_creates_new_task_definition_even_when_name_and_content_match(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create existing task definition with specific content
        $existingTaskDef = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'name'               => 'Existing Task',
            'description'        => 'Same Description',
            'task_runner_name'   => 'BaseTaskRunner',
            'task_runner_config' => ['option' => 'value'],
            'prompt'             => 'Same prompt',
        ]);

        $service = new WorkflowNodeClipboardImportService();

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
                        'name'                   => 'Existing Task',
                        'description'            => 'Same Description',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'task_runner_config'     => ['option' => 'value'],
                        'prompt'                 => 'Same prompt',
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
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);

        // Assert
        $this->assertCount(1, $createdNodes);
        $node = $createdNodes[0];

        // Verify a NEW TaskDefinition was created (not reused), even though content matches
        $this->assertNotEquals($existingTaskDef->id, $node->task_definition_id);

        // Verify the new TaskDefinition has a unique name (e.g., "Existing Task 2")
        $newTaskDef = TaskDefinition::find($node->task_definition_id);
        $this->assertNotNull($newTaskDef);
        $this->assertStringStartsWith('Existing Task', $newTaskDef->name);
        $this->assertNotEquals('Existing Task', $newTaskDef->name);

        // Verify TWO TaskDefinitions now exist with similar names
        $taskDefCount = TaskDefinition::where('team_id', $this->user->currentTeam->id)
            ->where('name', 'like', 'Existing Task%')
            ->count();
        $this->assertEquals(2, $taskDefCount);
    }

    public function test_imports_creates_new_task_definition_with_unique_name_when_name_matches_but_content_differs(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create existing task definition with same name but different content
        $existingTaskDef = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'name'             => 'Task Name',
            'description'      => 'Different Description',
            'task_runner_name' => 'BaseTaskRunner',
            'prompt'           => 'Different prompt',
        ]);

        $service = new WorkflowNodeClipboardImportService();

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
                        'name'                   => 'Task Name',
                        'description'            => 'New Description',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'task_runner_config'     => null,
                        'prompt'                 => 'New prompt',
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
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);

        // Assert
        $this->assertCount(1, $createdNodes);
        $node = $createdNodes[0];

        // Verify a NEW TaskDefinition was created (not reusing existing)
        $this->assertNotEquals($existingTaskDef->id, $node->task_definition_id);

        // Verify the new TaskDefinition has a unique name (with suffix)
        $newTaskDef = TaskDefinition::find($node->task_definition_id);
        $this->assertNotEquals('Task Name', $newTaskDef->name);
        $this->assertStringContainsString('Task Name', $newTaskDef->name); // Should contain original name

        // Verify two TaskDefinitions exist now
        $taskDefCount = TaskDefinition::where('team_id', $this->user->currentTeam->id)
            ->where('name', 'LIKE', 'Task Name%')
            ->count();
        $this->assertEquals(2, $taskDefCount);
    }

    public function test_imports_reuses_agent_when_name_and_model_match(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create existing agent
        $existingAgent = Agent::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Test Agent',
            'model'       => self::TEST_MODEL,
            'description' => 'Same description',
            'api_options' => ['temperature' => 0.7],
        ]);

        $service = new WorkflowNodeClipboardImportService();

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
                Agent::class => [
                    888 => [
                        'name'        => 'Test Agent',
                        'model'       => self::TEST_MODEL,
                        'description' => 'Same description',
                        'api_options' => ['temperature' => 0.7],
                    ],
                ],
                TaskDefinition::class => [
                    999 => [
                        'name'                   => 'Task With Agent',
                        'description'            => 'Test',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'task_runner_config'     => null,
                        'prompt'                 => 'Test',
                        'agent_id'               => 'App\Models\Agent\Agent:888',
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
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);

        // Assert
        $this->assertCount(1, $createdNodes);
        $node    = $createdNodes[0];
        $taskDef = TaskDefinition::find($node->task_definition_id);

        // Verify existing agent was reused
        $this->assertEquals($existingAgent->id, $taskDef->agent_id);

        // Verify only one agent with this name exists
        $agentCount = Agent::where('team_id', $this->user->currentTeam->id)
            ->where('name', 'Test Agent')
            ->count();
        $this->assertEquals(1, $agentCount);
    }

    public function test_imports_creates_new_agent_when_name_matches_but_model_differs(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create existing agent with same name but different model
        $existingAgent = Agent::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Test Agent',
            'model'       => 'gpt-3.5',
            'description' => 'Description',
        ]);

        $service = new WorkflowNodeClipboardImportService();

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
                Agent::class => [
                    888 => [
                        'name'        => 'Test Agent',
                        'model'       => self::TEST_MODEL, // Different model
                        'description' => 'Description',
                        'api_options' => ['temperature' => 0.7],
                    ],
                ],
                TaskDefinition::class => [
                    999 => [
                        'name'                   => 'Task With Agent',
                        'description'            => 'Test',
                        'task_runner_name'       => 'BaseTaskRunner',
                        'task_runner_config'     => null,
                        'prompt'                 => 'Test',
                        'agent_id'               => 'App\Models\Agent\Agent:888',
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
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);

        // Assert
        $this->assertCount(1, $createdNodes);
        $node    = $createdNodes[0];
        $taskDef = TaskDefinition::find($node->task_definition_id);

        // Verify a NEW agent was created
        $this->assertNotEquals($existingAgent->id, $taskDef->agent_id);

        // Verify the new agent has a unique name
        $newAgent = Agent::find($taskDef->agent_id);
        $this->assertNotEquals('Test Agent', $newAgent->name);
        $this->assertStringContainsString('Test Agent', $newAgent->name);

        // Verify two agents exist
        $agentCount = Agent::where('team_id', $this->user->currentTeam->id)
            ->where('name', 'LIKE', 'Test Agent%')
            ->count();
        $this->assertEquals(2, $agentCount);
    }

    public function test_applies_position_offset_correctly(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $service  = new WorkflowNodeClipboardImportService();

        // Nodes at original positions (100,200) and (300,400)
        // Centroid would be at (200, 300)
        $clipboardData = [
            'type'    => 'workflow-node-clipboard',
            'version' => '1.0',
            'nodes'   => [
                [
                    'export_key'          => 'node_0',
                    'name'                => 'Node 1',
                    'settings'            => ['x' => 100, 'y' => 200],
                    'params'              => [],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:999',
                ],
                [
                    'export_key'          => 'node_1',
                    'name'                => 'Node 2',
                    'settings'            => ['x' => 300, 'y' => 400],
                    'params'              => [],
                    'task_definition_ref' => 'App\Models\Task\TaskDefinition:998',
                ],
            ],
            'connections' => [],
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

        // Paste at (600, 700)
        // Offset should be (600 - 200, 700 - 300) = (400, 400)
        // Node 1 should be at (100 + 400, 200 + 400) = (500, 600)
        // Node 2 should be at (300 + 400, 400 + 400) = (700, 800)

        // Act
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 600, 'y' => 700]);

        // Assert
        $this->assertCount(2, $createdNodes);

        $node1 = WorkflowNode::where('name', 'Node 1')->first();
        $this->assertEquals(500, $node1->settings['x']);
        $this->assertEquals(600, $node1->settings['y']);

        $node2 = WorkflowNode::where('name', 'Node 2')->first();
        $this->assertEquals(700, $node2->settings['x']);
        $this->assertEquals(800, $node2->settings['y']);
    }

    public function test_creates_workflow_connections_between_imported_nodes(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $service  = new WorkflowNodeClipboardImportService();

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
                    'name'               => 'Test Connection',
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
        $createdNodes = $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);

        // Assert
        $this->assertCount(2, $createdNodes);

        // Verify connection was created
        $sourceNode = WorkflowNode::where('name', 'Source Node')->first();
        $targetNode = WorkflowNode::where('name', 'Target Node')->first();

        $connection = WorkflowConnection::where('workflow_definition_id', $workflow->id)
            ->where('source_node_id', $sourceNode->id)
            ->where('target_node_id', $targetNode->id)
            ->first();

        $this->assertNotNull($connection);
        $this->assertEquals('Test Connection', $connection->name);
        $this->assertEquals('output1', $connection->source_output_port);
        $this->assertEquals('input1', $connection->target_input_port);
    }

    public function test_rolls_back_on_validation_error(): void
    {
        // Arrange
        $workflow = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $service  = new WorkflowNodeClipboardImportService();

        // Invalid clipboard data - missing task definition reference
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
                // Missing TaskDefinition definition
            ],
        ];

        // Get counts before
        $nodeCountBefore    = WorkflowNode::count();
        $taskDefCountBefore = TaskDefinition::count();

        // Act & Assert
        $this->expectException(ValidationError::class);
        $service->importNodes($workflow, $clipboardData, ['x' => 0, 'y' => 0]);

        // Verify nothing was created (transaction rolled back)
        $this->assertEquals($nodeCountBefore, WorkflowNode::count());
        $this->assertEquals($taskDefCountBefore, TaskDefinition::count());
    }
}
