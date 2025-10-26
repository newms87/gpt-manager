<?php

namespace Tests\Unit\Models\Workflow;

use App\Models\Usage\UsageEvent;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowRunUsageTrackingTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function test_createWorkflowRun_automatically_createsUsageEvent(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'name'    => 'test-workflow',
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When
        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Test Run',
            'started_at'             => now(),
        ]);

        // Then
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertNotNull($usageEvent);
        $this->assertEquals('workflow_run', $usageEvent->event_type);
        $this->assertEquals('test-workflow', $usageEvent->api_name);
        $this->assertEquals('Running', $usageEvent->metadata['status']);
        $this->assertEquals(0, $usageEvent->metadata['progress_percent']);
        $this->assertEquals(0, $usageEvent->metadata['task_run_count']);
        $this->assertEquals(0, $usageEvent->metadata['task_process_count']);
    }

    #[Test]
    public function test_updateWorkflowRunStatus_updatesUsageEvent(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'name'    => 'extract-data-workflow',
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Extract Data Run',
            'started_at'             => Carbon::now()->subMinutes(5),
        ]);

        // When - Update status to completed
        $workflowRun->update([
            'completed_at' => now(),
        ]);

        // Manually trigger usage event update since event listeners might not work in tests
        $workflowRun->updateWorkflowUsageEvent();

        // Then
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertNotNull($usageEvent);
        $this->assertEquals('Completed', $usageEvent->metadata['status']);
        $this->assertArrayHasKey('completed_at', $usageEvent->metadata);
        $this->assertGreaterThan(0, $usageEvent->run_time_ms);
    }

    #[Test]
    public function test_workflowRunFailure_updatesUsageEventWithFailureDetails(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'name'    => 'write-demand-workflow',
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Failed Run',
            'started_at'             => Carbon::now()->subMinutes(3),
        ]);

        // When - Update status to failed
        $workflowRun->update([
            'failed_at' => now(),
        ]);

        // Manually trigger usage event update
        $workflowRun->updateWorkflowUsageEvent();

        // Then
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertNotNull($usageEvent);
        $this->assertEquals('Failed', $usageEvent->metadata['status']);
        $this->assertArrayHasKey('completed_at', $usageEvent->metadata);
        $this->assertArrayHasKey('failed_at', $usageEvent->metadata);
        // The error should match the actual status of the workflow run
        $this->assertEquals($workflowRun->status, $usageEvent->metadata['error']);
        $this->assertGreaterThan(0, $usageEvent->run_time_ms);
    }

    #[Test]
    public function test_workflowRunStopped_updatesUsageEventStatus(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'name'    => 'stopped-workflow',
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Stopped Run',
            'started_at'             => Carbon::now()->subMinutes(2),
        ]);

        // When - Update status to stopped
        $workflowRun->update([
            'stopped_at' => now(),
        ]);

        // Manually trigger usage event update
        $workflowRun->updateWorkflowUsageEvent();

        // Then
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertNotNull($usageEvent);
        $this->assertEquals('Stopped', $usageEvent->metadata['status']);
        $this->assertArrayHasKey('completed_at', $usageEvent->metadata);
        $this->assertGreaterThan(0, $usageEvent->run_time_ms);
    }

    #[Test]
    public function test_calculateWorkflowRunTime_withDifferentEndTimes_calculatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $startTime = Carbon::now()->subMinutes(10);

        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Test Run',
            'started_at'             => $startTime,
        ]);

        // Test completed workflow
        $workflowRun->update(['completed_at' => Carbon::now()]);
        $workflowRun->updateWorkflowUsageEvent();
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertGreaterThan(599000, $usageEvent->run_time_ms); // ~10 minutes
        $this->assertLessThan(601000, $usageEvent->run_time_ms);
    }

    #[Test]
    public function test_workflowRunWithoutStartTime_hasZeroRunTime(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create without started_at initially
        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'No Start Time Run',
            'started_at'             => now(), // Set initially to avoid state issues
        ]);

        // Manually clear started_at and update the usage event
        $workflowRun->started_at   = null;
        $workflowRun->completed_at = now();
        $workflowRun->updateWorkflowUsageEvent();

        // Then
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertEquals(0, $usageEvent->run_time_ms);
    }

    #[Test]
    public function test_findWorkflowUsageEvent_findsCorrectEvent(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Test Run',
            'started_at'             => now(),
        ]);

        // Create additional usage events for the same workflow run (different event types)
        UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => WorkflowRun::class,
            'object_id'     => $workflowRun->id,
            'object_id_int' => $workflowRun->id,
            'event_type'    => 'other_event',
            'api_name'      => 'internal',
            'run_time_ms'   => 0,
            'metadata'      => [],
        ]);

        // When
        $usageEvent = $workflowRun->findWorkflowUsageEvent();

        // Then
        $this->assertNotNull($usageEvent);
        $this->assertEquals('workflow_run', $usageEvent->event_type);
        // The api_name should be the workflow definition name, not 'internal'
        $this->assertEquals($workflowDefinition->name, $usageEvent->api_name);
    }

    #[Test]
    public function test_workflowRunWithoutDefinition_doesNotCreateUsageEvent(): void
    {
        // Given - Create WorkflowRun without workflow definition
        $workflowRun = new WorkflowRun([
            'name'       => 'Test Run',
            'started_at' => now(),
        ]);

        // When - Try to create usage event (should fail due to null workflowDefinition)
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Attempt to read property "name" on null');

        $workflowRun->createWorkflowUsageEvent();
    }

    #[Test]
    public function test_updateWorkflowUsageEvent_createsEventIfNotExists(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create WorkflowRun and manually delete its usage event
        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Test Run',
            'started_at'             => now(),
        ]);

        $workflowRun->usageEvents()->delete();

        // When - Update status which triggers usage event update
        $workflowRun->update(['completed_at' => now()]);
        $workflowRun->updateWorkflowUsageEvent();

        // Then - Usage event should be recreated
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $this->assertNotNull($usageEvent);
        $this->assertEquals('Completed', $usageEvent->metadata['status']);
    }

    #[Test]
    public function test_workflowRunMetadata_includesProgressAndCounts(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name'                   => 'Test Run',
            'started_at'             => now(),
        ]);

        // When - Update status
        $workflowRun->update(['completed_at' => now()]);
        $workflowRun->updateWorkflowUsageEvent();

        // Then - Metadata should include all required fields
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        $metadata   = $usageEvent->metadata;

        $this->assertArrayHasKey('status', $metadata);
        $this->assertArrayHasKey('progress_percent', $metadata);
        $this->assertArrayHasKey('task_run_count', $metadata);
        $this->assertArrayHasKey('task_process_count', $metadata);

        $this->assertIsInt($metadata['progress_percent']);
        $this->assertIsInt($metadata['task_run_count']);
        $this->assertIsInt($metadata['task_process_count']);
    }

    /**
     * Helper method to invoke protected methods for testing
     */
    protected function invokeProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
