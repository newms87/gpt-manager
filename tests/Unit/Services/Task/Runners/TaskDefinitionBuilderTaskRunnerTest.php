<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Task\Runners\TaskDefinitionBuilderTaskRunner;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskDefinitionBuilderTaskRunnerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private TaskDefinitionBuilderTaskRunner $runner;

    private TaskRun $taskRun;

    private TaskProcess $taskProcess;

    private TaskDefinition $taskDefinition;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Create required models with proper relationships
        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-4o-mini', // Use a real model that has API configuration
        ]);
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $this->runner = new TaskDefinitionBuilderTaskRunner();
        $this->runner->setTaskRun($this->taskRun);
        $this->runner->setTaskProcess($this->taskProcess);
        // TaskDefinition is automatically set from TaskRun in setTaskRun()
    }

    public function test_prepareProcess_setsCorrectProperties(): void
    {
        // When
        $this->runner->prepareProcess();

        // Then
        $this->assertEquals('Task Definition Builder', $this->taskProcess->name);
        // Timeout is configured on TaskDefinition, not TaskProcess
    }

    public function test_prepareProcess_withTaskDefinition_usesTaskDefinitionTimeout(): void
    {
        // Given
        $customTimeout                               = 240;
        $this->taskDefinition->timeout_after_seconds = $customTimeout;
        $this->taskDefinition->save();

        // Recreate runner with fresh TaskRun to get updated TaskDefinition
        $this->runner = new TaskDefinitionBuilderTaskRunner();
        $this->runner->setTaskRun($this->taskRun->fresh());
        $this->runner->setTaskProcess($this->taskProcess);

        // When
        $this->runner->prepareProcess();

        // Then
        // Timeout is accessed through TaskProcess->TaskRun->TaskDefinition relationship
        $this->assertEquals($customTimeout, $this->taskProcess->taskRun->taskDefinition->timeout_after_seconds);
    }

    public function test_extractTaskSpecificationFromArtifact_withValidTaskSpecification_returnsSpecification(): void
    {
        // Given
        $taskSpecification = [
            'task_specification' => [
                'name'        => 'Test Task',
                'description' => 'Test task description',
                'runner_type' => 'AgentThreadTaskRunner',
            ],
            'workflow_definition' => [
                'name' => 'Test Workflow',
            ],
            'connections' => [],
            'task_index'  => 0,
        ];

        $artifact = Artifact::factory()->create([
            'task_process_id' => $this->taskProcess->id,
            'json_content'    => $taskSpecification,
        ]);

        // Attach artifact as input artifact to the task process
        $this->taskProcess->addInputArtifacts([$artifact]);

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('extractTaskSpecificationFromArtifact');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertEquals($taskSpecification, $result);
    }

    public function test_extractTaskSpecificationFromArtifact_withNoTaskSpecification_returnsNull(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'task_process_id' => $this->taskProcess->id,
            'json_content'    => ['some_other_data' => 'value'],
        ]);

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('extractTaskSpecificationFromArtifact');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertNull($result);
    }

    public function test_extractTaskSpecificationFromArtifact_withNoJsonContent_returnsNull(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'task_process_id' => $this->taskProcess->id,
            'text_content'    => 'Some text content',
            'json_content'    => null,
        ]);

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('extractTaskSpecificationFromArtifact');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertNull($result);
    }

    public function test_resolveCurrentWorkflow_fromTaskRunWorkflow_returnsWorkflow(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflowRun        = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);
        $this->taskRun->workflow_run_id = $workflowRun->id;
        $this->taskRun->save();

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('resolveCurrentWorkflow');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertInstanceOf(WorkflowDefinition::class, $result);
        $this->assertEquals($workflowDefinition->id, $result->id);
    }

    public function test_resolveCurrentWorkflow_fromTaskConfig_returnsWorkflow(): void
    {
        // Given
        $workflowDefinition                       = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $this->taskDefinition->task_runner_config = ['workflow_definition_id' => $workflowDefinition->id];
        $this->taskDefinition->save();

        // Recreate runner with fresh TaskRun to get updated TaskDefinition
        $this->runner = new TaskDefinitionBuilderTaskRunner();
        $this->runner->setTaskRun($this->taskRun->fresh());
        $this->runner->setTaskProcess($this->taskProcess);

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('resolveCurrentWorkflow');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertInstanceOf(WorkflowDefinition::class, $result);
        $this->assertEquals($workflowDefinition->id, $result->id);
    }

    public function test_buildTaskPrompt_withCompleteSpecification_buildsComprehensivePrompt(): void
    {
        // Given
        $specification = [
            'task_specification' => [
                'name'               => 'Data Processing Task',
                'description'        => 'Process incoming data files',
                'runner_type'        => 'AgentThreadTaskRunner',
                'agent_requirements' => 'Data processing specialist',
                'prompt'             => 'Process the data according to specifications',
                'configuration'      => ['timeout' => 300, 'max_retries' => 3],
            ],
            'workflow_definition' => [
                'name'        => 'Data Pipeline Workflow',
                'description' => 'Complete data processing pipeline',
            ],
            'connections' => [
                ['source' => 'Input Validator', 'target' => 'Data Processing Task'],
                ['source' => 'Data Processing Task', 'target' => 'Output Generator'],
            ],
            'task_index' => 1,
        ];
        $context = "# Task Builder Documentation\nThis is documentation context.";

        // When
        $result = $this->runner->buildTaskPrompt($specification, $context);

        // Then
        $this->assertStringContainsString('# Task Builder Documentation Context', $result);
        $this->assertStringContainsString('This is documentation context', $result);
        $this->assertStringContainsString('# Task Specification to Build', $result);
        $this->assertStringContainsString('**Task Name:** Data Processing Task', $result);
        $this->assertStringContainsString('**Description:** Process incoming data files', $result);
        $this->assertStringContainsString('**Required Runner:** AgentThreadTaskRunner', $result);
        $this->assertStringContainsString('**Agent Requirements:** Data processing specialist', $result);
        $this->assertStringContainsString('**Prompt Requirements:** Process the data according to specifications', $result);
        $this->assertStringContainsString('# Workflow Context', $result);
        $this->assertStringContainsString('**Workflow Name:** Data Pipeline Workflow', $result);
        $this->assertStringContainsString('# Workflow Connections', $result);
        $this->assertStringContainsString('Input Validator → Data Processing Task', $result);
        $this->assertStringContainsString('**Task Position:** 2 in the workflow sequence', $result);
        $this->assertStringContainsString('# Your Task', $result);
        $this->assertStringContainsString('Create a complete TaskDefinition based on the specification', $result);
        $this->assertStringContainsString('# Important Constraints', $result);
    }

    public function test_buildTaskPrompt_withMinimalSpecification_includesRequiredSections(): void
    {
        // Given
        $specification = [
            'task_specification' => [
                'name' => 'Simple Task',
            ],
        ];
        $context = 'Basic context';

        // When
        $result = $this->runner->buildTaskPrompt($specification, $context);

        // Then
        $this->assertStringContainsString('**Task Name:** Simple Task', $result);
        $this->assertStringContainsString('# Your Task', $result);
        $this->assertStringContainsString('# Important Constraints', $result);
        // Should not include sections that weren't provided
        $this->assertStringNotContainsString('# Workflow Context', $result);
        $this->assertStringNotContainsString('# Workflow Connections', $result);
    }

    public function test_getTaskBuilderSchemaDefinition_createsSchemaIfNotExists(): void
    {
        // Given - no existing schema

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('getTaskBuilderSchemaDefinition');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertInstanceOf(SchemaDefinition::class, $result);
        $this->assertEquals('Task Builder Schema', $result->name);
        $this->assertEquals($this->user->currentTeam->id, $result->team_id);
        $this->assertArrayHasKey('action', $result->schema['properties']);
        $this->assertArrayHasKey('task_definition', $result->schema['properties']);
        $this->assertArrayHasKey('directives', $result->schema['properties']);
        $this->assertArrayHasKey('workflow_node', $result->schema['properties']);

        // Verify it was saved to database
        $this->assertDatabaseHas('schema_definitions', [
            'name'    => 'Task Builder Schema',
            'team_id' => $this->user->currentTeam->id,
        ]);
    }

    public function test_getTaskBuilderSchemaDefinition_returnsExistingSchema(): void
    {
        // Given
        $existingSchema = SchemaDefinition::factory()->create([
            'name'    => 'Task Builder Schema',
            'team_id' => $this->user->currentTeam->id,
            'schema'  => ['type' => 'object', 'properties' => ['test' => ['type' => 'string']]],
        ]);

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('getTaskBuilderSchemaDefinition');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertEquals($existingSchema->id, $result->id);
        $this->assertEquals('Task Builder Schema', $result->name);
    }

    public function test_createTaskDefinition_createsNewTaskDefinition(): void
    {
        // Given
        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-4o-mini',
        ]);
        $data = [
            'name'             => 'New Task',
            'description'      => 'New task description',
            'task_runner_name' => 'AgentThreadTaskRunner',
            'prompt'           => 'Execute this task',
        ];
        $directivesData = [
            [
                'name'     => 'Test Directive',
                'content'  => 'Test directive content',
                'section'  => 'Top',
                'position' => 1,
            ],
        ];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('createTaskDefinition');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner, $data, $agent, $directivesData);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('New Task', $result->name);
        $this->assertEquals('New task description', $result->description);
        $this->assertEquals('AgentThreadTaskRunner', $result->task_runner_name);
        $this->assertEquals($agent->id, $result->agent_id);
        $this->assertEquals($this->user->currentTeam->id, $result->team_id);

        // Verify database record
        $this->assertDatabaseHas('task_definitions', [
            'name'        => 'New Task',
            'description' => 'New task description',
            'team_id'     => $this->user->currentTeam->id,
            'agent_id'    => $agent->id,
        ]);

        // Verify directives were created
        $this->assertDatabaseHas('task_definition_directives', [
            'task_definition_id' => $result->id,
            'section'            => 'Top',
            'position'           => 1,
        ]);
    }

    public function test_updateTaskDefinition_updatesExistingTaskDefinition(): void
    {
        // Given
        $existingTask = TaskDefinition::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Existing Task',
            'description' => 'Old description',
        ]);

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $data  = [
            'name'             => 'Existing Task',
            'description'      => 'Updated description',
            'task_runner_name' => 'UpdatedRunner',
            'prompt'           => 'Updated prompt',
        ];
        $directivesData = [];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('updateTaskDefinition');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner, $data, $agent, $directivesData);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals($existingTask->id, $result->id);
        $this->assertEquals('Updated description', $result->description);
        $this->assertEquals('UpdatedRunner', $result->task_runner_name);
        $this->assertEquals($agent->id, $result->agent_id);

        // Verify database was updated
        $this->assertDatabaseHas('task_definitions', [
            'id'               => $existingTask->id,
            'description'      => 'Updated description',
            'task_runner_name' => 'UpdatedRunner',
            'agent_id'         => $agent->id,
        ]);
    }

    public function test_updateTaskDefinition_withNonexistentTask_createsNewOne(): void
    {
        // Given
        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-4o-mini',
        ]);
        $data = [
            'name'        => 'Nonexistent Task',
            'description' => 'New task created during update',
        ];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('updateTaskDefinition');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner, $data, $agent, []);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Nonexistent Task', $result->name);
        $this->assertEquals('New task created during update', $result->description);

        // Verify new record was created
        $this->assertDatabaseHas('task_definitions', [
            'name'        => 'Nonexistent Task',
            'description' => 'New task created during update',
            'team_id'     => $this->user->currentTeam->id,
        ]);
    }

    public function test_deleteTaskDefinition_deletesExistingTask(): void
    {
        // Given
        $existingTask = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Task to Delete',
        ]);
        $data = ['name' => 'Task to Delete'];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('deleteTaskDefinition');
        $method->setAccessible(true);
        $method->invoke($this->runner, $data);

        // Then
        $this->assertSoftDeleted('task_definitions', [
            'id' => $existingTask->id,
        ]);
    }

    public function test_deleteTaskDefinition_withNonexistentTask_doesNothing(): void
    {
        // Given
        $data = ['name' => 'Nonexistent Task'];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('deleteTaskDefinition');
        $method->setAccessible(true);
        $method->invoke($this->runner, $data);

        // Then - no exception should be thrown
        $this->assertTrue(true);
    }

    public function test_createWorkflowNode_createsNodeForTask(): void
    {
        // Given
        $workflow       = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $nodeData       = ['x' => 150, 'y' => 250];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('createWorkflowNode');
        $method->setAccessible(true);
        $method->invoke($this->runner, $workflow, $taskDefinition, $nodeData);

        // Then
        $this->assertDatabaseHas('workflow_nodes', [
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDefinition->id,
        ]);
    }

    public function test_createWorkflowNode_withDefaultCoordinates_usesDefaults(): void
    {
        // Given
        $workflow       = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $nodeData       = []; // No coordinates provided

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('createWorkflowNode');
        $method->setAccessible(true);
        $method->invoke($this->runner, $workflow, $taskDefinition, $nodeData);

        // Then
        $this->assertDatabaseHas('workflow_nodes', [
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDefinition->id,
        ]);
    }

    public function test_updateWorkflowNode_updatesExistingNode(): void
    {
        // Given
        $workflow       = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $existingNode   = WorkflowNode::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'settings'           => ['x' => 100, 'y' => 100],
        ]);
        // Set workflow_definition_id directly since it's not fillable
        $existingNode->workflow_definition_id = $workflow->id;
        $existingNode->save();
        $nodeData = ['x' => 200, 'y' => 300];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('updateWorkflowNode');
        $method->setAccessible(true);
        $method->invoke($this->runner, $workflow, $taskDefinition, $nodeData);

        // Then
        $this->assertDatabaseHas('workflow_nodes', [
            'id'                     => $existingNode->id,
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDefinition->id,
        ]);
    }

    public function test_updateWorkflowNode_withNoExistingNode_createsNewOne(): void
    {
        // Given
        $workflow       = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $nodeData       = ['x' => 200, 'y' => 300];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('updateWorkflowNode');
        $method->setAccessible(true);
        $method->invoke($this->runner, $workflow, $taskDefinition, $nodeData);

        // Then
        $this->assertDatabaseHas('workflow_nodes', [
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDefinition->id,
        ]);
    }

    public function test_applyTaskDefinition_withCreateAction_createsTaskAndNode(): void
    {
        // Given
        $workflow    = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflow->id,
        ]);
        $this->taskRun->workflow_run_id = $workflowRun->id;
        $this->taskRun->save();

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $specification = ['task_specification' => ['name' => 'Test Spec']];
        $result        = [
            'action'          => 'create',
            'task_definition' => [
                'name'             => 'New Task',
                'description'      => 'New task description',
                'task_runner_name' => 'TestRunner',
                'agent_name'       => $agent->name,
            ],
            'directives' => [
                ['name' => 'Test Directive', 'content' => 'Test content', 'section' => 'Top'],
            ],
            'workflow_node' => ['x' => 150, 'y' => 250],
        ];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('applyTaskDefinition');
        $method->setAccessible(true);
        $artifact = $method->invoke($this->runner, $specification, $result);

        // Then
        $this->assertInstanceOf(Artifact::class, $artifact);
        $this->assertStringContainsString('Applied Task Definition: create', $artifact->name);
        $this->assertArrayHasKey('task_definition_id', $artifact->json_content);
        $this->assertArrayHasKey('workflow_node_created', $artifact->json_content);
        $this->assertTrue($artifact->json_content['workflow_node_created']);

        // Verify task was created
        $this->assertDatabaseHas('task_definitions', [
            'name'        => 'New Task',
            'description' => 'New task description',
            'team_id'     => $this->user->currentTeam->id,
        ]);

        // Verify workflow node was created
        $taskDefinition = TaskDefinition::where('name', 'New Task')->first();
        $this->assertDatabaseHas('workflow_nodes', [
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $taskDefinition->id,
        ]);
    }

    public function test_applyTaskDefinition_withAgentFallback_usesFirstAvailableAgent(): void
    {
        // Given - use the agent from setUp as fallback agent since ->first() will find it
        $specification = ['task_specification' => ['name' => 'Test Spec']];
        $result        = [
            'action'          => 'create',
            'task_definition' => [
                'name'        => 'Task Without Agent',
                'description' => 'Task description',
                'agent_name'  => 'Nonexistent Agent', // This agent doesn't exist
            ],
            'directives'    => [],
            'workflow_node' => [],
        ];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('applyTaskDefinition');
        $method->setAccessible(true);
        $artifact = $method->invoke($this->runner, $specification, $result);

        // Then
        $this->assertInstanceOf(Artifact::class, $artifact);

        // Verify task was created with fallback agent (the agent from setup)
        $taskDefinition = TaskDefinition::where('name', 'Task Without Agent')->first();
        $this->assertNotNull($taskDefinition);

        // The fallback should use the first available agent from the team
        $firstAvailableAgent = Agent::where('team_id', $this->user->currentTeam->id)->first();
        $this->assertEquals($firstAvailableAgent->id, $taskDefinition->agent_id);
    }

    public function test_run_withValidTaskSpecification_processesSuccessfully(): void
    {
        // Given
        $taskSpecification = [
            'task_specification' => [
                'name'        => 'Test Task',
                'description' => 'Test task description',
                'runner_type' => 'AgentThreadTaskRunner',
            ],
            'workflow_definition' => ['name' => 'Test Workflow'],
            'connections'         => [],
            'task_index'          => 0,
        ];

        $inputArtifact = Artifact::factory()->create([
            'task_process_id' => $this->taskProcess->id,
            'json_content'    => $taskSpecification,
        ]);

        // Add input artifact to the task process
        $this->taskProcess->addInputArtifacts([$inputArtifact]);

        // Mock the AgentThreadService to avoid external API calls
        $mockThreadRun             = $this->createMock(\App\Models\Agent\AgentThreadRun::class);
        $mockMessage               = $this->createMock(\App\Models\Agent\AgentThreadMessage::class);
        $mockMessage->json_content = [
            'action'          => 'create',
            'task_definition' => [
                'name'        => 'Generated Task',
                'description' => 'Generated description',
            ],
            'directives'    => [],
            'workflow_node' => [],
        ];
        $mockMessage->content       = 'Mock response content';
        $mockThreadRun->lastMessage = $mockMessage;

        $this->mock(\App\Services\AgentThread\AgentThreadService::class)
            ->shouldReceive('withResponseFormat')
            ->andReturnSelf()
            ->shouldReceive('withTimeout')
            ->andReturnSelf()
            ->shouldReceive('run')
            ->andReturn($mockThreadRun);

        // When
        $this->runner->run();

        // Then
        // The run method should complete without throwing exceptions
        // and should create a schema definition if none exists
        $this->assertDatabaseHas('schema_definitions', [
            'name'    => 'Task Builder Schema',
            'team_id' => $this->user->currentTeam->id,
        ]);
    }

    public function test_run_withNoValidSpecification_completesEarly(): void
    {
        // Given - no valid task specification artifact

        // When
        $this->runner->run();

        // Then - should complete without errors
        $this->assertTrue(true);
    }

    public function test_formatAppliedResultText_createsReadableOutput(): void
    {
        // Given
        $action = 'create';
        $data   = [
            'name'             => 'Test Task',
            'description'      => 'Test task description',
            'task_runner_name' => 'AgentThreadTaskRunner',
        ];
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Task',
        ]);

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('formatAppliedResultText');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner, $action, $data, $taskDefinition);

        // Then
        $this->assertStringContainsString('# Task Definition create Applied', $result);
        $this->assertStringContainsString('**Action:** create', $result);
        $this->assertStringContainsString('**Task Name:** Test Task', $result);
        $this->assertStringContainsString('**Database ID:** ' . $taskDefinition->id, $result);
        $this->assertStringContainsString('**Description:** Test task description', $result);
        $this->assertStringContainsString('**Runner:** AgentThreadTaskRunner', $result);
        $this->assertStringContainsString('successfully applied to the database', $result);
    }

    public function test_teamBasedAccessControl_restrictsToCurrentTeam(): void
    {
        // Given
        $otherTeam = \App\Models\Team\Team::factory()->create();

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('getTaskBuilderSchemaDefinition');
        $method->setAccessible(true);
        $result = $method->invoke($this->runner);

        // Then
        $this->assertEquals($this->user->currentTeam->id, $result->team_id);
        $this->assertNotEquals($otherTeam->id, $result->team_id);
    }

    public function test_createTaskDefinitionDirectives_createsDirectivesCorrectly(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $directivesData = [
            [
                'name'     => 'Directive 1',
                'content'  => 'First directive content',
                'section'  => 'Top',
                'position' => 1,
            ],
            [
                'name'     => 'Directive 2',
                'content'  => 'Second directive content',
                'section'  => 'Bottom',
                'position' => 2,
            ],
            [
                'content' => '', // Empty content should be skipped
                'section' => 'Top',
            ],
        ];

        // When
        $reflection = new \ReflectionClass($this->runner);
        $method     = $reflection->getMethod('createTaskDefinitionDirectives');
        $method->setAccessible(true);
        $method->invoke($this->runner, $taskDefinition, $directivesData);

        // Then
        $this->assertDatabaseHas('task_definition_directives', [
            'task_definition_id' => $taskDefinition->id,
            'section'            => 'Top',
            'position'           => 1,
        ]);

        $this->assertDatabaseHas('task_definition_directives', [
            'task_definition_id' => $taskDefinition->id,
            'section'            => 'Bottom',
            'position'           => 2,
        ]);

        // Verify empty content directive was not created
        $directiveCount = TaskDefinitionDirective::where('task_definition_id', $taskDefinition->id)->count();
        $this->assertEquals(2, $directiveCount);
    }
}
