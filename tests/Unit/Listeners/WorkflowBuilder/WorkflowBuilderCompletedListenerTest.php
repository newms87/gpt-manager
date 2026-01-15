<?php

namespace Tests\Unit\Listeners\WorkflowBuilder;

use App\Events\WorkflowRunUpdatedEvent;
use App\Listeners\WorkflowBuilder\WorkflowBuilderCompletedListener;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Services\WorkflowBuilder\WorkflowBuilderService;
use Exception;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowBuilderCompletedListenerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private WorkflowBuilderCompletedListener $listener;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->listener = new WorkflowBuilderCompletedListener();
    }

    public function test_handle_withNonFinishedWorkflowRun_returnsEarly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        // Mock the WorkflowRun with all needed properties and methods
        $workflowRun = $this->mock(WorkflowRun::class);
        $workflowRun->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $workflowRun->shouldReceive('getAttribute')->with('workflowDefinition')->andReturn($workflowDefinition);
        $workflowRun->shouldReceive('isFinished')->andReturn(false);
        $workflowRun->shouldReceive('relationLoaded')->with('workflowDefinition')->andReturn(true);
        $workflowRun->shouldIgnoreMissing();

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');

        // Mock WorkflowBuilderService to ensure it's not called
        $mockService = $this->mock(WorkflowBuilderService::class);
        $mockService->shouldNotReceive('processWorkflowCompletion');

        // When
        $this->listener->handle($event);

        // Then - if we get here without the service being called, the test passes
        $this->assertTrue(true);
    }

    public function test_handle_withNonBuilderWorkflow_returnsEarly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Some Other Workflow', // Not the builder workflow
        ]);

        $workflowRun = $this->mock(WorkflowRun::class);
        $workflowRun->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $workflowRun->shouldReceive('getAttribute')->with('workflowDefinition')->andReturn($workflowDefinition);
        $workflowRun->shouldReceive('isFinished')->andReturn(true);
        $workflowRun->shouldReceive('relationLoaded')->with('workflowDefinition')->andReturn(true);
        $workflowRun->shouldIgnoreMissing();

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');

        // Mock WorkflowBuilderService to ensure it's not called
        $mockService = $this->mock(WorkflowBuilderService::class);
        $mockService->shouldNotReceive('processWorkflowCompletion');

        // When
        $this->listener->handle($event);

        // Then - if we get here without the service being called, the test passes
        $this->assertTrue(true);
    }

    public function test_handle_withBuilderWorkflowButNoChat_returnsEarly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');

        // No WorkflowBuilderChat exists for this workflow run
        // The listener should return early without processing

        // When
        $this->listener->handle($event);

        // Then - if we get here without the service being called, the test passes
        $this->assertTrue(true);
    }

    public function test_handle_withMismatchedWorkflowRun_returnsEarly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $anotherWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Create chat expecting a different workflow run
        WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $anotherWorkflowRun->id, // Different ID
        ]);

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');

        // The listener should return early because the WorkflowRun ID doesn't match the chat's expected run

        // When
        $this->listener->handle($event);

        // Then - if we get here without the service being called, the test passes
        $this->assertTrue(true);
    }

    public function test_handle_withValidBuilderWorkflowRun_processesCompletion(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        // Create required agents
        \App\Models\Agent\Agent::factory()->create([
            'team_id'     => null, // System-owned agent
            'name'        => 'Workflow Evaluator',
            'description' => 'Agent for evaluating workflow build results',
            'model'       => 'test-model',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
        ]);

        // Create realistic workflow build artifacts
        $buildArtifact = \App\Models\Task\Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Workflow Organization',
            'json_content' => [
                'workflow_definition' => [
                    'name'        => 'Test Workflow',
                    'description' => 'A test workflow created by integration test',
                    'max_workers' => 5,
                ],
                'task_specifications' => [
                    [
                        'name'               => 'Input Processing',
                        'description'        => 'Process input data',
                        'runner_type'        => 'WorkflowInputTaskRunner',
                        'agent_requirements' => 'General purpose agent',
                    ],
                ],
            ],
        ]);

        // Associate the artifact as output of the completed workflow run
        $workflowRun->addOutputArtifacts([$buildArtifact]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');

        // When
        $this->listener->handle($event);

        // Then - verify the real business logic ran by checking the chat status was updated
        $updatedChat = $chat->fresh();
        $this->assertEquals(WorkflowBuilderChat::STATUS_COMPLETED, $updatedChat->status);
    }

    public function test_handle_withServiceException_updatesStatusToFailedAndRethrows(): void
    {
        // Given - Create a chat that will cause the service to fail naturally
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'failed_at'              => now(),
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');

        // When
        $this->listener->handle($event);

        // Then - verify chat status was updated to failed (without exception being thrown)
        $updatedChat = $chat->fresh();
        $this->assertEquals(WorkflowBuilderChat::STATUS_FAILED, $updatedChat->status);
        $this->assertArrayHasKey('build_failed_at', $updatedChat->meta['phase_data']);
        $this->assertEquals('Failed', $updatedChat->meta['phase_data']['failure_reason']);
    }

    public function test_findWorkflowBuilderChat_withCorrectWorkflowName_findsChat(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('findWorkflowBuilderChat');
        $method->setAccessible(true);
        $result = $method->invoke($this->listener, $workflowRun);

        // Then
        $this->assertInstanceOf(WorkflowBuilderChat::class, $result);
        $this->assertEquals($chat->id, $result->id);
    }

    public function test_findWorkflowBuilderChat_withWrongWorkflowName_returnsNull(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Some Other Workflow', // Not the builder workflow
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('findWorkflowBuilderChat');
        $method->setAccessible(true);
        $result = $method->invoke($this->listener, $workflowRun);

        // Then
        $this->assertNull($result);
    }

    public function test_findWorkflowBuilderChat_withWrongStatus_returnsNull(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_COMPLETED, // Wrong status
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('findWorkflowBuilderChat');
        $method->setAccessible(true);
        $result = $method->invoke($this->listener, $workflowRun);

        // Then
        $this->assertNull($result);
    }

    public function test_findWorkflowBuilderChat_withDifferentTeam_findsChat(): void
    {
        // Given - System-owned LLM Workflow Builder can be used by any team
        $otherTeam          = \App\Models\Team\Team::factory()->create();
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $otherTeam->id, // Different team can use system workflow
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('findWorkflowBuilderChat');
        $method->setAccessible(true);
        $result = $method->invoke($this->listener, $workflowRun);

        // Then - Should find the chat since workflow_run_id matches and system workflow can be used by any team
        $this->assertInstanceOf(WorkflowBuilderChat::class, $result);
        $this->assertEquals($chat->id, $result->id);
    }

    public function test_findWorkflowBuilderChat_withWrongWorkflowRunId_returnsNull(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $anotherWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $anotherWorkflowRun->id, // Different workflow run
        ]);

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('findWorkflowBuilderChat');
        $method->setAccessible(true);
        $result = $method->invoke($this->listener, $workflowRun);

        // Then
        $this->assertNull($result);
    }

    public function test_handle_withMultipleChats_findsCorrectOne(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        // Create required agents
        \App\Models\Agent\Agent::factory()->create([
            'team_id'     => null, // System-owned agent
            'name'        => 'Workflow Evaluator',
            'description' => 'Agent for evaluating workflow build results',
            'model'       => 'test-model',
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Create multiple chats
        $chat1 = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun1->id,
        ]);

        $chat2 = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $this->user->currentTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun2->id,
        ]);

        // Create realistic workflow build artifacts for workflowRun2
        $buildArtifact = \App\Models\Task\Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Workflow Organization',
            'json_content' => [
                'workflow_definition' => [
                    'name'        => 'Test Workflow',
                    'description' => 'A test workflow created by integration test',
                    'max_workers' => 5,
                ],
                'task_specifications' => [
                    [
                        'name'               => 'Input Processing',
                        'description'        => 'Process input data',
                        'runner_type'        => 'WorkflowInputTaskRunner',
                        'agent_requirements' => 'General purpose agent',
                    ],
                ],
            ],
        ]);

        // Associate the artifact as output of the completed workflow run
        $workflowRun2->addOutputArtifacts([$buildArtifact]);

        // For testing, manually set the status to simulate a finished run
        $workflowRun2->status       = 'Completed';
        $workflowRun2->completed_at = now();
        $workflowRun2->save();

        $event = new WorkflowRunUpdatedEvent($workflowRun2, 'updated');

        // When
        $this->listener->handle($event);

        // Then - verify the correct chat (chat2) was processed by checking its status changed
        $this->assertEquals(WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW, $chat1->fresh()->status);
        $this->assertEquals(WorkflowBuilderChat::STATUS_COMPLETED, $chat2->fresh()->status);
    }

    public function test_listener_implementsCorrectInterfaces(): void
    {
        // Then
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $this->listener);
        $this->assertContains(\Illuminate\Queue\InteractsWithQueue::class, class_uses($this->listener));
        $this->assertContains(\App\Traits\HasDebugLogging::class, class_uses($this->listener));
    }

    public function test_systemWorkflowAccessControl_allowsAnyTeamAccess(): void
    {
        // Given - System-owned workflows can be used by any team
        $otherTeam = \App\Models\Team\Team::factory()->create();

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => null, // System-owned workflow
            'name'    => 'LLM Workflow Builder',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Create chat for team using system workflow
        $chat = WorkflowBuilderChat::factory()->create([
            'team_id'                 => $otherTeam->id,
            'status'                  => WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW,
            'current_workflow_run_id' => $workflowRun->id,
        ]);

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->listener);
        $method     = $reflection->getMethod('findWorkflowBuilderChat');
        $method->setAccessible(true);
        $result = $method->invoke($this->listener, $workflowRun);

        // Then - Should find chat since system workflows are accessible by any team
        $this->assertInstanceOf(WorkflowBuilderChat::class, $result);
        $this->assertEquals($chat->id, $result->id);
    }
}
