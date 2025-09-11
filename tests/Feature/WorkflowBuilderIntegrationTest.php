<?php

namespace Tests\Feature;

use App\Events\WorkflowBuilderChatUpdatedEvent;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Services\WorkflowBuilder\WorkflowBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowBuilderIntegrationTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected WorkflowBuilderService $workflowBuilderService;
    protected array $agents;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->workflowBuilderService = app(WorkflowBuilderService::class);
        
        // Configure test environment
        Config::set('ai.models.test-model', [
            'api'     => \Tests\Feature\Api\TestAi\TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
        ]);

        // Mock queue to prevent actual job dispatching
        Queue::fake();
        
        // Create required agents for testing
        $this->agents = $this->createTestAgents();
    }

    public function test_startRequirementsGathering_createsNewChatAndInitiatesPlanningConversation(): void
    {
        // Given
        $prompt = "Create a content analysis workflow that extracts key insights from documents";

        // When
        $chat = $this->workflowBuilderService->startRequirementsGathering($prompt);

        // Then
        $this->assertInstanceOf(WorkflowBuilderChat::class, $chat);
        $this->assertEquals(WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING, $chat->status);
        $this->assertNotNull($chat->workflow_input_id);
        $this->assertNotNull($chat->agent_thread_id);
        $this->assertEquals($this->user->currentTeam->id, $chat->team_id);
        
        // Verify WorkflowInput was created
        $this->assertDatabaseHas('workflow_inputs', [
            'id' => $chat->workflow_input_id,
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        // Verify AgentThread was created with messages
        $this->assertDatabaseHas('agent_threads', [
            'id' => $chat->agent_thread_id,
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        // Verify agent thread has system and user messages
        $messages = $chat->agentThread->messages;
        $this->assertGreaterThanOrEqual(2, $messages->count());
        $this->assertEquals('system', $messages->first()->role);
        $this->assertEquals('user', $messages->last()->role);
        $this->assertStringContainsString($prompt, $messages->last()->content);
    }

    public function test_generateWorkflowPlan_extractsPlanFromResponse(): void
    {
        // Given - Create a chat with an agent thread and existing messages
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $this->agents['planner']->id,
        ]);

        // Add some existing messages to simulate a conversation
        $agentThread->messages()->create([
            'role' => 'system',
            'content' => 'You are a workflow planning assistant.',
            'team_id' => $this->user->currentTeam->id,
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_thread_id' => $agentThread->id,
            'status' => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING,
        ]);

        // When - Test the private method directly since AgentThreadService calls would require complex setup
        $mockMessage = (object) [
            'id' => 123,
            'content' => "Workflow Name: Content Analysis Pipeline\nDescription: Analyzes documents for key insights\n\n1. Document Ingestion: Load and preprocess documents\n2. Content Analysis: Extract key insights using NLP\n3. Result Compilation: Compile findings into report",
            'json_content' => null
        ];

        $plan = $this->callPrivateMethod($this->workflowBuilderService, 'extractPlanFromResponse', [$mockMessage]);

        // Then
        $this->assertIsArray($plan);
        $this->assertArrayHasKey('workflow_name', $plan);
        $this->assertArrayHasKey('tasks', $plan);
        $this->assertEquals('Content Analysis Pipeline', $plan['workflow_name']);
        $this->assertCount(3, $plan['tasks']);
        $this->assertEquals('Document Ingestion', $plan['tasks'][0]['name']);
    }

    public function test_extractPlanFromResponse_withJsonContent_returnsParsedPlan(): void
    {
        // Given
        $mockMessage = (object) [
            'id' => 456,
            'content' => 'Here is the workflow plan...',
            'json_content' => [
                'workflow_name' => 'Data Processing Workflow',
                'description' => 'Processes data through multiple stages',
                'max_workers' => 3,
                'tasks' => [
                    [
                        'name' => 'Data Validation',
                        'description' => 'Validate input data format',
                        'runner_type' => 'AgentThreadTaskRunner'
                    ],
                    [
                        'name' => 'Data Processing',
                        'description' => 'Process validated data',
                        'runner_type' => 'AgentThreadTaskRunner'
                    ]
                ]
            ]
        ];

        // When
        $plan = $this->callPrivateMethod($this->workflowBuilderService, 'extractPlanFromResponse', [$mockMessage]);

        // Then
        $this->assertEquals('Data Processing Workflow', $plan['workflow_name']);
        $this->assertEquals('Processes data through multiple stages', $plan['description']);
        $this->assertEquals(3, $plan['max_workers']);
        $this->assertCount(2, $plan['tasks']);
        $this->assertEquals('json', $plan['source_type']);
    }

    public function test_extractPlanFromResponse_withTextContent_fallsBackToTextExtraction(): void
    {
        // Given
        $mockMessage = (object) [
            'id' => 789,
            'content' => "Workflow Name: Text Analysis Flow\nDescription: Analyzes text documents\n\n1. Text Preprocessing: Clean and prepare text\n2. Analysis: Perform text analysis\n3. Report Generation: Generate final report",
            'json_content' => null
        ];

        // When
        $plan = $this->callPrivateMethod($this->workflowBuilderService, 'extractPlanFromResponse', [$mockMessage]);

        // Then
        $this->assertEquals('Text Analysis Flow', $plan['workflow_name']);
        $this->assertEquals('text', $plan['source_type']);
        $this->assertCount(3, $plan['tasks']);
        $this->assertEquals('Text Preprocessing', $plan['tasks'][0]['name']);
    }

    public function test_applyWorkflowChanges_withBuildArtifacts_createsWorkflowAndTasks(): void
    {
        // Given - chat without existing workflow definition
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'workflow_definition_id' => null, // No existing workflow
        ]);
        
        $buildArtifacts = [
            [
                'name' => 'Workflow Organization',
                'content' => [
                    'workflow_definition' => [
                        'name' => 'Test Workflow',
                        'description' => 'A test workflow created by integration test',
                        'max_workers' => 5
                    ],
                    'task_specifications' => [
                        [
                            'name' => 'Input Processing',
                            'description' => 'Process input data',
                            'runner_type' => 'WorkflowInputTaskRunner',
                            'agent_requirements' => 'General purpose agent'
                        ],
                        [
                            'name' => 'Data Analysis',
                            'description' => 'Analyze processed data',
                            'runner_type' => 'AgentThreadTaskRunner',
                            'agent_requirements' => 'Analytical agent'
                        ]
                    ],
                    'connections' => [
                        [
                            'source' => 'Input Processing',
                            'target' => 'Data Analysis'
                        ]
                    ]
                ]
            ]
        ];

        // When - debug the parsing first
        $workflowData = $this->callPrivateMethod(
            $this->workflowBuilderService, 
            'parseWorkflowFromArtifacts', 
            [$buildArtifacts]
        );
        
        $workflowDefinition = $this->callPrivateMethod(
            $this->workflowBuilderService, 
            'applyWorkflowChanges', 
            [$chat, $buildArtifacts]
        );

        // Then
        $this->assertInstanceOf(WorkflowDefinition::class, $workflowDefinition);
        $this->assertEquals('Test Workflow', $workflowDefinition->name);
        $this->assertEquals('A test workflow created by integration test', $workflowDefinition->description);
        $this->assertEquals(5, $workflowDefinition->max_workers);
        
        // Verify tasks were created
        $workflowDefinition->load('workflowNodes.taskDefinition');
        $this->assertCount(2, $workflowDefinition->workflowNodes);
        
        $taskNames = $workflowDefinition->workflowNodes->pluck('taskDefinition.name')->toArray();
        $this->assertContains('Input Processing', $taskNames);
        $this->assertContains('Data Analysis', $taskNames);
        
        // Verify connections were created
        $this->assertCount(1, $workflowDefinition->workflowConnections);
    }

    public function test_attachArtifacts_broadcastsEvent(): void
    {
        // Given
        Event::fake();
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $artifacts = [
            ['name' => 'test_artifact', 'content' => 'test_content']
        ];

        // When
        $chat->attachArtifacts($artifacts);

        // Then
        Event::assertDispatched(WorkflowBuilderChatUpdatedEvent::class, function ($event) use ($chat, $artifacts) {
            return $event->chat->id === $chat->id && 
                   $event->updateType === 'artifacts' && 
                   $event->data === $artifacts;
        });
        
        // Verify artifacts stored in meta
        $this->assertEquals($artifacts, $chat->fresh()->meta['artifacts']);
    }

    public function test_phaseTransitionValidation_allowsValidTransitionsOnly(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING
        ]);

        // When & Then - Valid transition
        $chat->updatePhase(WorkflowBuilderChat::STATUS_ANALYZING_PLAN);
        $this->assertEquals(WorkflowBuilderChat::STATUS_ANALYZING_PLAN, $chat->fresh()->status);

        // Invalid transition should throw exception
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $chat->updatePhase(WorkflowBuilderChat::STATUS_COMPLETED);
    }

    private function createTestAgents(): array
    {
        $planner = Agent::factory()->create([
            'team_id' => null, // System-owned agent
            'name' => 'Workflow Planner',
            'description' => 'Test planning agent',
            'model' => 'test-model',
        ]);

        $evaluator = Agent::factory()->create([
            'team_id' => null, // System-owned agent
            'name' => 'Workflow Evaluator',
            'description' => 'Test evaluation agent',
            'model' => 'test-model',
        ]);

        return compact('planner', 'evaluator');
    }

    private function callPrivateMethod($object, $method, $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}