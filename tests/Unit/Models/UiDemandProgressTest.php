<?php

namespace Tests\Unit\Models;

use App\Models\Demand\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandProgressTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_workflowRuns_relationship_works_correctly()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // When
        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // Then
        $this->assertEquals(1, $uiDemand->workflowRuns()->count());
        $this->assertEquals($workflowRun->id, $uiDemand->workflowRuns()->first()->id);
        $this->assertEquals('extract_data', $uiDemand->workflowRuns()->first()->pivot->workflow_type);
    }

    public function test_workflowRuns_filters_by_workflow_type_extract_data()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $writeDemandWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflow->id => ['workflow_type' => 'extract_data'],
            $writeDemandWorkflow->id => ['workflow_type' => 'write_demand_letter'],
        ]);

        // When
        $extractDataWorkflows = $uiDemand->workflowRuns()->where('workflow_type', 'extract_data')->get();

        // Then
        $this->assertEquals(1, $extractDataWorkflows->count());
        $this->assertEquals($extractDataWorkflow->id, $extractDataWorkflows->first()->id);
    }

    public function test_workflowRuns_filters_by_workflow_type_write_demand_letter()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $writeDemandWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflow->id => ['workflow_type' => 'extract_data'],
            $writeDemandWorkflow->id => ['workflow_type' => 'write_demand_letter'],
        ]);

        // When
        $writeDemandWorkflows = $uiDemand->workflowRuns()->where('workflow_type', 'write_demand_letter')->get();

        // Then
        $this->assertEquals(1, $writeDemandWorkflows->count());
        $this->assertEquals($writeDemandWorkflow->id, $writeDemandWorkflows->first()->id);
    }

    public function test_multiple_workflows_can_be_attached_to_one_demand()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflow1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflow2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // When
        $uiDemand->workflowRuns()->attach([
            $workflow1->id => ['workflow_type' => 'extract_data'],
            $workflow2->id => ['workflow_type' => 'extract_data'],
        ]);

        // Then
        $this->assertEquals(2, $uiDemand->workflowRuns()->where('workflow_type', 'extract_data')->count());
        $this->assertEquals(2, $uiDemand->workflowRuns()->count());
    }

    public function test_getLatestWorkflowRun_returns_most_recent_extract_data_workflow()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $olderWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at'             => now()->subHour(),
        ]);

        $newerWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at'             => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $olderWorkflow->id => ['workflow_type' => 'extract_data'],
            $newerWorkflow->id => ['workflow_type' => 'extract_data'],
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestWorkflowRun('extract_data');

        // Then
        $this->assertNotNull($latestWorkflow);
        $this->assertEquals($newerWorkflow->id, $latestWorkflow->id);
    }

    public function test_getLatestWorkflowRun_returns_most_recent_write_demand_letter_workflow()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $olderWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at'             => now()->subHour(),
        ]);

        $newerWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at'             => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $olderWorkflow->id => ['workflow_type' => 'write_demand_letter'],
            $newerWorkflow->id => ['workflow_type' => 'write_demand_letter'],
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestWorkflowRun('write_demand_letter');

        // Then
        $this->assertNotNull($latestWorkflow);
        $this->assertEquals($newerWorkflow->id, $latestWorkflow->id);
    }

    public function test_getLatestWorkflowRun_returns_null_when_no_extract_data_workflows_exist()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestWorkflowRun('extract_data');

        // Then
        $this->assertNull($latestWorkflow);
    }

    public function test_getLatestWorkflowRun_returns_null_when_no_write_demand_letter_workflows_exist()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestWorkflowRun('write_demand_letter');

        // Then
        $this->assertNull($latestWorkflow);
    }

    public function test_isWorkflowRunning_returns_true_when_extract_data_workflow_is_running()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('extract_data');

        // Then
        $this->assertTrue($isRunning);
    }

    public function test_isWorkflowRunning_returns_false_when_no_extract_data_workflow_exists()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('extract_data');

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_isWorkflowRunning_returns_false_when_extract_data_workflow_is_completed()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('extract_data');

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_isWorkflowRunning_returns_true_when_write_demand_letter_workflow_is_running()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'write_demand_letter',
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('write_demand_letter');

        // Then
        $this->assertTrue($isRunning);
    }

    public function test_isWorkflowRunning_returns_false_when_no_write_demand_letter_workflow_exists()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('write_demand_letter');

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_isWorkflowRunning_returns_false_when_write_demand_letter_workflow_is_completed()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'write_demand_letter',
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('write_demand_letter');

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_both_extract_data_and_write_demand_can_be_running_simultaneously()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $writeDemandWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflow->id => ['workflow_type' => 'extract_data'],
            $writeDemandWorkflow->id => ['workflow_type' => 'write_demand_letter'],
        ]);

        // When
        $isExtractDataRunning = $uiDemand->isWorkflowRunning('extract_data');
        $isWriteDemandRunning = $uiDemand->isWorkflowRunning('write_demand_letter');

        // Then
        $this->assertTrue($isExtractDataRunning);
        $this->assertTrue($isWriteDemandRunning);
    }

    public function test_workflow_attachment_preserves_pivot_data()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // When
        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // Then
        $attachedWorkflow = $uiDemand->workflowRuns()->first();
        $this->assertEquals('extract_data', $attachedWorkflow->pivot->workflow_type);
        $this->assertNotNull($attachedWorkflow->pivot->created_at);
        $this->assertNotNull($attachedWorkflow->pivot->updated_at);
    }

    public function test_running_status_detection_with_pending_workflows()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            // No started_at, stopped_at, completed_at, or failed_at - defaults to pending
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When
        $isRunning = $uiDemand->isWorkflowRunning('extract_data');

        // Then
        $this->assertTrue($isRunning);
    }
}
