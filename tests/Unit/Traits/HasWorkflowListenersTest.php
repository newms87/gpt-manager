<?php

namespace Tests\Unit\Traits;

use App\Models\UiDemand;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use Tests\AuthenticatedTestCase;

class HasWorkflowListenersTest extends AuthenticatedTestCase
{
    protected UiDemand $model;

    public function setUp(): void
    {
        parent::setUp();
        $this->model = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);
    }

    public function test_workflow_listeners_relationship()
    {
        $workflowRun = WorkflowRun::factory()->create();

        $workflowListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        $listeners = $this->model->workflowListeners;

        $this->assertCount(1, $listeners);
        $this->assertEquals($workflowListener->id, $listeners->first()->id);
    }

    public function test_get_workflow_listener_by_type()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        $extractListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        $writeListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );

        // Test getting specific workflow type
        $foundExtractListener = $this->model->getWorkflowListener(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA);
        $foundWriteListener = $this->model->getWorkflowListener(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND);

        $this->assertEquals($extractListener->id, $foundExtractListener->id);
        $this->assertEquals($writeListener->id, $foundWriteListener->id);

        // Test getting non-existent workflow type
        $nonExistentListener = $this->model->getWorkflowListener('non_existent_type');
        $this->assertNull($nonExistentListener);
    }

    public function test_has_workflow_of_type()
    {
        $workflowRun = WorkflowRun::factory()->create();

        WorkflowListener::createForListener(
            $this->model,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        $this->assertTrue($this->model->hasWorkflowOfType(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA));
        $this->assertFalse($this->model->hasWorkflowOfType(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND));
    }

    public function test_is_workflow_running()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        $runningListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $runningListener->markAsRunning();

        $completedListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );
        $completedListener->markAsCompleted();

        $this->assertTrue($this->model->isWorkflowRunning(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA));
        $this->assertFalse($this->model->isWorkflowRunning(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND));
    }

    public function test_is_workflow_completed()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        $completedListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $completedListener->markAsCompleted();

        $runningListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );
        $runningListener->markAsRunning();

        $this->assertTrue($this->model->isWorkflowCompleted(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA));
        $this->assertFalse($this->model->isWorkflowCompleted(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND));
    }

    public function test_is_workflow_failed()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        $failedListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $failedListener->markAsFailed();

        $completedListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );
        $completedListener->markAsCompleted();

        $this->assertTrue($this->model->isWorkflowFailed(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA));
        $this->assertFalse($this->model->isWorkflowFailed(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND));
    }

    public function test_get_workflow_status()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        $runningListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $runningListener->markAsRunning();

        $completedListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );
        $completedListener->markAsCompleted();

        $extractStatus = $this->model->getWorkflowStatus(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA);
        $writeStatus = $this->model->getWorkflowStatus(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND);
        $nonExistentStatus = $this->model->getWorkflowStatus('non_existent_type');

        $this->assertEquals(WorkflowListener::STATUS_RUNNING, $extractStatus);
        $this->assertEquals(WorkflowListener::STATUS_COMPLETED, $writeStatus);
        $this->assertNull($nonExistentStatus);
    }

    public function test_get_running_workflows()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();
        $workflowRun3 = WorkflowRun::factory()->create();

        $runningListener1 = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $runningListener1->markAsRunning();

        $runningListener2 = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );
        $runningListener2->markAsRunning();

        $completedListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun3,
            'other_workflow_type'
        );
        $completedListener->markAsCompleted();

        $runningWorkflows = $this->model->getRunningWorkflows();

        $this->assertCount(2, $runningWorkflows);
        $runningIds = $runningWorkflows->pluck('id')->toArray();
        $this->assertContains($runningListener1->id, $runningIds);
        $this->assertContains($runningListener2->id, $runningIds);
        $this->assertNotContains($completedListener->id, $runningIds);
    }

    public function test_has_running_workflows()
    {
        // Initially no running workflows
        $this->assertFalse($this->model->hasRunningWorkflows());

        $workflowRun = WorkflowRun::factory()->create();

        $listener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Still no running workflows (listener is pending)
        $this->assertFalse($this->model->hasRunningWorkflows());

        // Mark as running
        $listener->markAsRunning();
        $this->assertTrue($this->model->hasRunningWorkflows());

        // Mark as completed
        $listener->markAsCompleted();
        $this->assertFalse($this->model->hasRunningWorkflows());
    }

    public function test_get_latest_workflow_of_type()
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        // Create first listener
        $firstListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Sleep to ensure different timestamps
        sleep(1);

        // Create second listener of same type
        $secondListener = WorkflowListener::createForListener(
            $this->model,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        $latestListener = $this->model->getLatestWorkflowOfType(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA);

        // Should return the most recent listener
        $this->assertEquals($secondListener->id, $latestListener->id);

        // Test non-existent workflow type
        $nonExistentListener = $this->model->getLatestWorkflowOfType('non_existent_type');
        $this->assertNull($nonExistentListener);
    }
}