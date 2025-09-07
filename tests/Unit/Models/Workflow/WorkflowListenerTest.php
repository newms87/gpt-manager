<?php

namespace Tests\Unit\Models\Workflow;

use App\Models\Demand\UiDemand;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use Tests\AuthenticatedTestCase;

class WorkflowListenerTest extends AuthenticatedTestCase
{
    public function test_creates_workflow_listener_for_model()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create();

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA,
            ['test_key' => 'test_value']
        );

        $this->assertDatabaseHas('workflow_listeners', [
            'id'              => $workflowListener->id,
            'team_id'         => $this->user->currentTeam->id,
            'workflow_run_id' => $workflowRun->id,
            'listener_type'   => UiDemand::class,
            'listener_id'     => $uiDemand->id,
            'workflow_type'   => WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA,
            'status'          => WorkflowListener::STATUS_PENDING,
        ]);

        $this->assertEquals(['test_key' => 'test_value'], $workflowListener->metadata);
    }

    public function test_finds_workflow_listener_for_workflow_run()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create();

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        $foundListener = WorkflowListener::findForWorkflowRun($workflowRun);

        $this->assertNotNull($foundListener);
        $this->assertEquals($workflowListener->id, $foundListener->id);
    }

    public function test_workflow_listener_status_transitions()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create();

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Test pending to running
        $this->assertEquals(WorkflowListener::STATUS_PENDING, $workflowListener->status);

        $workflowListener->markAsRunning();
        $this->assertEquals(WorkflowListener::STATUS_RUNNING, $workflowListener->fresh()->status);
        $this->assertNotNull($workflowListener->fresh()->started_at);

        // Test running to completed
        $workflowListener->markAsCompleted();
        $this->assertEquals(WorkflowListener::STATUS_COMPLETED, $workflowListener->fresh()->status);
        $this->assertNotNull($workflowListener->fresh()->completed_at);

        // Test fresh listener for failed status
        $failedListener = WorkflowListener::createForListener(
            $uiDemand,
            WorkflowRun::factory()->create(),
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER
        );

        $failedListener->markAsFailed();
        $this->assertEquals(WorkflowListener::STATUS_FAILED, $failedListener->fresh()->status);
        $this->assertNotNull($failedListener->fresh()->failed_at);
    }

    public function test_workflow_listener_scopes()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();

        $extractListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun1,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $extractListener->markAsRunning();

        $writeListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun2,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER
        );
        $writeListener->markAsCompleted();

        // Test forWorkflowType scope
        $extractListeners = WorkflowListener::forWorkflowType(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA)->get();
        $this->assertCount(1, $extractListeners);
        $this->assertEquals($extractListener->id, $extractListeners->first()->id);

        // Test status scopes
        $runningListeners = WorkflowListener::running()->get();
        $this->assertCount(1, $runningListeners);
        $this->assertEquals($extractListener->id, $runningListeners->first()->id);

        $completedListeners = WorkflowListener::completed()->get();
        $this->assertCount(1, $completedListeners);
        $this->assertEquals($writeListener->id, $completedListeners->first()->id);
    }

    public function test_workflow_listener_polymorphic_relationship()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create();

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Test polymorphic relationship
        $this->assertInstanceOf(UiDemand::class, $workflowListener->listener);
        $this->assertEquals($uiDemand->id, $workflowListener->listener->id);
        $this->assertEquals($uiDemand->title, $workflowListener->listener->title);
    }
}
