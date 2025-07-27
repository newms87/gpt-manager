<?php

namespace Tests\Unit\Listeners;

use App\Events\WorkflowRunUpdatedEvent;
use App\Listeners\WorkflowListenerCompletedListener;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\AuthenticatedTestCase;

class WorkflowListenerCompletedListenerTest extends AuthenticatedTestCase
{
    public function test_handles_workflow_run_updated_event_for_ui_demand()
    {
        Event::fake();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Mock the UiDemandWorkflowService
        $mockService = Mockery::mock(UiDemandWorkflowService::class);
        $mockService->shouldReceive('handleUiDemandWorkflowComplete')
            ->once()
            ->with($workflowRun);

        $this->app->instance(UiDemandWorkflowService::class, $mockService);

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $listener = new WorkflowListenerCompletedListener();

        $listener->handle($event);

        // Service should have been called once
        $mockService->shouldHaveReceived('handleUiDemandWorkflowComplete')->once();
    }

    public function test_handles_workflow_run_updated_event_for_non_ui_demand()
    {
        Event::fake();

        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // No workflow listener exists - should not call service
        $mockService = Mockery::mock(UiDemandWorkflowService::class);
        $mockService->shouldReceive('handleUiDemandWorkflowComplete')
            ->never();

        $this->app->instance(UiDemandWorkflowService::class, $mockService);

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $listener = new WorkflowListenerCompletedListener();

        $listener->handle($event);

        // Service should not have been called
        $mockService->shouldNotHaveReceived('handleUiDemandWorkflowComplete');
    }

    public function test_handles_workflow_run_updated_event_only_for_completed_or_failed_runs()
    {
        Event::fake();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);

        // Test with running workflow (should not trigger)
        $runningWorkflowRun = WorkflowRun::factory()->create([
            'status' => 'running',
        ]);

        WorkflowListener::createForListener(
            $uiDemand,
            $runningWorkflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        $mockService = Mockery::mock(UiDemandWorkflowService::class);
        $mockService->shouldReceive('handleUiDemandWorkflowComplete')->never();

        $this->app->instance(UiDemandWorkflowService::class, $mockService);

        $event = new WorkflowRunUpdatedEvent($runningWorkflowRun, 'updated');
        $listener = new WorkflowListenerCompletedListener();

        $listener->handle($event);

        // Service should not have been called for running workflow
        $mockService->shouldNotHaveReceived('handleUiDemandWorkflowComplete');

        // Test with completed workflow (should trigger)
        $completedWorkflowRun = WorkflowRun::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        WorkflowListener::createForListener(
            $uiDemand,
            $completedWorkflowRun,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );

        $mockService2 = Mockery::mock(UiDemandWorkflowService::class);
        $mockService2->shouldReceive('handleUiDemandWorkflowComplete')
            ->once()
            ->with($completedWorkflowRun);

        $this->app->instance(UiDemandWorkflowService::class, $mockService2);

        $completedEvent = new WorkflowRunUpdatedEvent($completedWorkflowRun, 'updated');
        $listener->handle($completedEvent);

        // Service should have been called for completed workflow
        $mockService2->shouldHaveReceived('handleUiDemandWorkflowComplete')->once();
    }

    public function test_listener_is_registered_for_workflow_run_updated_event()
    {
        // This test ensures the listener is properly registered in EventServiceProvider
        $listeners = Event::getListeners(WorkflowRunUpdatedEvent::class);
        
        // We expect at least one listener to be registered for this event
        $this->assertNotEmpty($listeners, 'WorkflowRunUpdatedEvent should have listeners registered');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}