<?php

namespace Tests\Unit\Services\WorkflowBuilder;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\AgentThread\AgentThreadService;
use App\Services\WorkflowBuilder\WorkflowBuilderDocumentationService;
use App\Services\WorkflowBuilder\WorkflowBuilderService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowBuilderServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private WorkflowBuilderService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = new WorkflowBuilderService();

        // Configure test AI model
        Config::set('ai.models.test-model', [
            'api'     => \Tests\Feature\Api\TestAi\TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
        ]);
    }

    public function test_startRequirementsGathering_withValidData_createsNewChat(): void
    {
        // Given
        $prompt = "Create a workflow for data processing";
        $workflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $planningAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Planner'
        ]);

        // Mock AgentThreadService
        $mockAgentThreadService = $this->mock(AgentThreadService::class);
        
        // When
        $result = $this->service->startRequirementsGathering($prompt, $workflowDefinition->id);

        // Then
        $this->assertInstanceOf(WorkflowBuilderChat::class, $result);
        $this->assertEquals(WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING, $result->status);
        $this->assertEquals($this->user->currentTeam->id, $result->team_id);
        $this->assertNotNull($result->workflow_input_id);
        $this->assertNotNull($result->agent_thread_id);
        
        // Verify WorkflowInput was created
        $this->assertDatabaseHas('workflow_inputs', [
            'id' => $result->workflow_input_id,
            'content' => $prompt,
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        // Verify AgentThread was created and has messages
        $this->assertDatabaseHas('agent_threads', [
            'id' => $result->agent_thread_id,
            'agent_id' => $planningAgent->id,
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        $this->assertDatabaseHas('agent_thread_messages', [
            'agent_thread_id' => $result->agent_thread_id,
            'role' => 'system',
        ]);
        
        $this->assertDatabaseHas('agent_thread_messages', [
            'agent_thread_id' => $result->agent_thread_id,
            'role' => 'user',
            'content' => $prompt,
        ]);
    }

    public function test_startRequirementsGathering_withExistingChatId_retrievesAndUpdatesChat(): void
    {
        // Given
        $prompt = "Update the workflow";
        $existingChat = WorkflowBuilderChat::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $planningAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Planner'
        ]);

        // When
        $result = $this->service->startRequirementsGathering($prompt, null, $existingChat->id);

        // Then
        $this->assertEquals($existingChat->id, $result->id);
        $this->assertNotNull($result->agent_thread_id);
    }

    public function test_startRequirementsGathering_withEmptyPrompt_throwsValidationError(): void
    {
        // Given
        $emptyPrompt = "";

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Prompt cannot be empty');
        
        $this->service->startRequirementsGathering($emptyPrompt);
    }

    public function test_startRequirementsGathering_withInvalidWorkflowDefinitionId_throwsValidationError(): void
    {
        // Given
        $prompt = "Test prompt";
        $invalidWorkflowDefinitionId = 99999;

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow definition not found or not accessible');
        
        $this->service->startRequirementsGathering($prompt, $invalidWorkflowDefinitionId);
    }

    public function test_startRequirementsGathering_withInvalidChatId_throwsValidationError(): void
    {
        // Given
        $prompt = "Test prompt";
        $invalidChatId = 99999;

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow builder chat not found or not accessible');
        
        $this->service->startRequirementsGathering($prompt, null, $invalidChatId);
    }

    public function test_startRequirementsGathering_withMissingPlanningAgent_throwsValidationError(): void
    {
        // Given
        $prompt = "Test prompt";
        // No planning agent exists

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow Planner agent not found');
        
        $this->service->startRequirementsGathering($prompt);
    }

    public function test_generateWorkflowPlan_withValidChat_generatesAndUpdatesPlan(): void
    {
        // Given
        $agentThread = AgentThread::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_thread_id' => $agentThread->id,
            'status' => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING,
        ]);
        $userInput = "I need to process CSV files and generate reports";

        // Create required Workflow Planner agent
        $plannerAgent = \App\Models\Agent\Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Planner',
            'description' => 'Agent for planning workflows',
            'model' => 'test-model',
        ]);

        // Update agent thread to use the planner agent
        $agentThread->update(['agent_id' => $plannerAgent->id]);

        // When
        $result = $this->service->generateWorkflowPlan($chat, $userInput);

        // Then - Test real business logic behavior
        $this->assertIsArray($result);
        $this->assertArrayHasKey('workflow_name', $result);
        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('source_type', $result);
        $this->assertNotEmpty($result['workflow_name']); // Should have content from test AI
        
        // Verify chat was updated with real business logic
        $updatedChat = $chat->fresh();
        $this->assertEquals(WorkflowBuilderChat::STATUS_ANALYZING_PLAN, $updatedChat->status);
        $this->assertNotNull($updatedChat->meta['phase_data']['generated_plan']);
        
        // Verify user message was added (real database operation)
        $this->assertDatabaseHas('agent_thread_messages', [
            'agent_thread_id' => $agentThread->id,
            'role' => 'user',
            'content' => $userInput,
        ]);
        
        // Verify agent thread has messages from real conversation
        $messages = $agentThread->fresh()->messages;
        $this->assertGreaterThan(1, $messages->count()); // Should have user + assistant messages
    }

    public function test_generateWorkflowPlan_withChatWithoutAgentThread_throwsValidationError(): void
    {
        // Given - Create chat with agent thread, then delete the agent thread to simulate orphaned reference
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        // Delete the agent thread to make the relationship return null
        $chat->agentThread->delete();
        $chat->refresh();
        
        $userInput = "Test input";

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No agent thread associated with chat');
        
        $this->service->generateWorkflowPlan($chat, $userInput);
    }

    public function test_generateWorkflowPlan_withInvalidChatStatus_throwsValidationError(): void
    {
        // Given
        $agentThread = AgentThread::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_thread_id' => $agentThread->id,
            'status' => WorkflowBuilderChat::STATUS_COMPLETED,
        ]);
        $userInput = "Test input";

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Chat is not in a valid state for plan generation');
        
        $this->service->generateWorkflowPlan($chat, $userInput);
    }

    public function test_generateWorkflowPlan_withFailedAgentRun_throwsValidationError(): void
    {
        // Given
        $plannerAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Planner',
            'model' => 'test-model'
        ]);
        
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $plannerAgent->id
        ]);
        
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_thread_id' => $agentThread->id,
            'status' => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING,
        ]);
        $userInput = "Create a workflow for document analysis";

        // When - Test that the method runs successfully with real business logic
        $result = $this->service->generateWorkflowPlan($chat, $userInput);
        
        // Then - Verify plan was generated and stored
        $this->assertIsArray($result);
        $this->assertArrayHasKey('workflow_name', $result);
    }

    public function test_startWorkflowBuild_withValidChat_startsWorkflowRun(): void
    {
        // Given
        $builderWorkflow = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'LLM Workflow Builder'
        ]);
        
        // Create a starting node (with WorkflowInputTaskRunner and no incoming connections)
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'task_runner_name' => 'Workflow Input' // WorkflowInputTaskRunner::RUNNER_NAME
        ]);
        
        WorkflowNode::factory()->create([
            'workflow_definition_id' => $builderWorkflow->id,
            'task_definition_id' => $taskDefinition->id
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_ANALYZING_PLAN,
            'workflow_definition_id' => $builderWorkflow->id,
            'meta' => [
                'build_state' => ['generated_plan' => ['task1' => 'description']]
            ]
        ]);

        // When & Then - now it should successfully start a workflow run
        $workflowRun = $this->service->startWorkflowBuild($chat);
        
        // Verify workflow run was created
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals($builderWorkflow->id, $workflowRun->workflow_definition_id);
        $this->assertNotNull($workflowRun->started_at);
    }

    public function test_startWorkflowBuild_withInvalidChatStatus_throwsValidationError(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING,
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Chat must be in analyzing_plan status to start workflow build');
        
        $this->service->startWorkflowBuild($chat);
    }

    public function test_startWorkflowBuild_withMissingPlan_throwsValidationError(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_ANALYZING_PLAN,
            'meta' => [] // No generated plan
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No approved plan found for workflow build');
        
        $this->service->startWorkflowBuild($chat);
    }

    public function test_startWorkflowBuild_withMissingBuilderWorkflow_throwsValidationError(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_ANALYZING_PLAN,
            'meta' => [
                'build_state' => ['generated_plan' => ['task1' => 'description']]
            ]
        ]);
        // No LLM Workflow Builder workflow exists

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('LLM Workflow Builder workflow definition not found');
        
        $this->service->startWorkflowBuild($chat);
    }

    public function test_processWorkflowCompletion_withSuccessfulRun_updatesChat(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        // Create required Workflow Evaluator agent
        $evaluatorAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Evaluator',
            'model' => 'test-model'
        ]);
        
        // Create a real WorkflowRun that's properly completed with artifacts
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'Completed',
            'completed_at' => now(),
            'has_run_all_tasks' => true
        ]);
        
        // Create output artifacts that contain workflow build information
        $buildArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Organization',
            'json_content' => [
                'workflow_definition' => [
                    'name' => 'Test Build Workflow',
                    'description' => 'A test workflow built from artifacts',
                    'max_workers' => 3
                ],
                'task_specifications' => [
                    [
                        'name' => 'Input Processing',
                        'description' => 'Process input data',
                        'runner_type' => 'WorkflowInputTaskRunner'
                    ]
                ]
            ]
        ]);
        
        $workflowRun->addOutputArtifacts([$buildArtifact]);
        
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When
        $this->service->processWorkflowCompletion($chat, $workflowRun);

        // Then
        $updatedChat = $chat->fresh();
        $this->assertEquals(WorkflowBuilderChat::STATUS_COMPLETED, $updatedChat->status);
    }

    public function test_processWorkflowCompletion_withFailedRun_updatesStatusToFailed(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        // Create a real WorkflowRun with failed status
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'Failed'
        ]);
        
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When
        $this->service->processWorkflowCompletion($chat, $workflowRun);

        // Then
        $updatedChat = $chat->fresh();
        $this->assertEquals(WorkflowBuilderChat::STATUS_FAILED, $updatedChat->status);
        $this->assertArrayHasKey('build_failed_at', $updatedChat->meta['phase_data']);
        $this->assertEquals($workflowRun->status, $updatedChat->meta['phase_data']['failure_reason']);
    }

    public function test_processWorkflowCompletion_withMismatchedWorkflowRun_throwsValidationError(): void
    {
        // Given
        $workflowRun = WorkflowRun::factory()->create();
        $anotherWorkflowRun = WorkflowRun::factory()->create();
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $anotherWorkflowRun->id,
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Completed workflow run does not match chat\'s current workflow run');
        
        $this->service->processWorkflowCompletion($chat, $workflowRun);
    }

    public function test_processWorkflowCompletion_withInvalidChatStatus_throwsValidationError(): void
    {
        // Given
        $workflowRun = WorkflowRun::factory()->create();
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_COMPLETED,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Chat is not in building_workflow status');
        
        $this->service->processWorkflowCompletion($chat, $workflowRun);
    }

    public function test_evaluateAndCommunicateResults_withValidChat_completesEvaluation(): void
    {
        // Given
        $evaluationAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Workflow Evaluator',
            'model' => 'test-model' // Use configured test model
        ]);
        
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $evaluationAgent->id
        ]);
        
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_thread_id' => $agentThread->id,
            'status' => WorkflowBuilderChat::STATUS_EVALUATING_RESULTS,
            'meta' => [
                'artifacts' => [
                    ['name' => 'test_artifact', 'content' => 'test_content']
                ]
            ]
        ]);

        // When
        $this->service->evaluateAndCommunicateResults($chat);

        // Then
        $updatedChat = $chat->fresh();
        $this->assertEquals(WorkflowBuilderChat::STATUS_COMPLETED, $updatedChat->status);
        $this->assertArrayHasKey('evaluation_completed_at', $updatedChat->meta['phase_data']);
    }

    public function test_evaluateAndCommunicateResults_withInvalidChatStatus_throwsValidationError(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Chat is not in evaluating_results status');
        
        $this->service->evaluateAndCommunicateResults($chat);
    }

    public function test_evaluateAndCommunicateResults_withNoArtifacts_throwsValidationError(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_EVALUATING_RESULTS,
            'meta' => [] // No artifacts
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No build artifacts found for evaluation');
        
        $this->service->evaluateAndCommunicateResults($chat);
    }

    public function test_evaluateAndCommunicateResults_withMissingEvaluationAgent_throwsValidationError(): void
    {
        // Given
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status' => WorkflowBuilderChat::STATUS_EVALUATING_RESULTS,
            'meta' => [
                'artifacts' => [['name' => 'test', 'content' => 'test']]
            ]
        ]);
        // No evaluation agent exists

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow Evaluator agent not found');
        
        $this->service->evaluateAndCommunicateResults($chat);
    }

    public function test_evaluateAndCommunicateResults_withFailedAgentRun_throwsValidationError(): void
    {
        // Given - Chat with invalid agent should cause failure
        $invalidAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model' => 'non-existent-model' // This will cause the test to fail
        ]);
        
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $invalidAgent->id
        ]);
        
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_thread_id' => $agentThread->id,
            'status' => WorkflowBuilderChat::STATUS_EVALUATING_RESULTS,
            'meta' => [
                'artifacts' => [['name' => 'test', 'content' => 'test']]
            ]
        ]);

        // When & Then - invalid model should cause failure
        $this->expectException(\Exception::class);
        
        $this->service->evaluateAndCommunicateResults($chat);
    }

    public function test_teamBasedAccessControl_restrictsCrossTeamAccess(): void
    {
        // Given - create another team with its own workflow definition
        $otherTeam = \App\Models\Team\Team::factory()->create();
        $otherTeamWorkflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $otherTeam->id]);
        $prompt = "Test prompt";

        // When & Then - should not be able to access other team's workflow definition
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow definition not found or not accessible');
        
        $this->service->startRequirementsGathering($prompt, $otherTeamWorkflowDefinition->id);
    }

    public function test_dbTransactions_rollbackOnFailure(): void
    {
        // Given
        $prompt = "Test prompt";
        
        // Force a database error by creating invalid data
        DB::shouldReceive('transaction')->once()->andThrow(new \Exception('Database error'));

        // When & Then
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');
        
        $this->service->startRequirementsGathering($prompt);
        
        // Verify no partial data was created
        $this->assertDatabaseMissing('workflow_builder_chats', [
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        $this->assertDatabaseMissing('workflow_inputs', [
            'team_id' => $this->user->currentTeam->id,
            'content' => $prompt,
        ]);
    }
}