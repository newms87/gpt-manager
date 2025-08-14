<?php

namespace Tests\Unit\Models;

use App\Models\Task\TaskRun;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Then
        $this->assertEquals(1, $uiDemand->workflowRuns()->count());
        $this->assertEquals($workflowRun->id, $uiDemand->workflowRuns()->first()->id);
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_EXTRACT_DATA, $uiDemand->workflowRuns()->first()->pivot->workflow_type);
    }

    public function test_extractDataWorkflowRuns_returns_only_extract_data_workflows()
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
            $extractDataWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $writeDemandWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND],
        ]);

        // When
        $extractDataWorkflows = $uiDemand->extractDataWorkflowRuns()->get();

        // Then
        $this->assertEquals(1, $extractDataWorkflows->count());
        $this->assertEquals($extractDataWorkflow->id, $extractDataWorkflows->first()->id);
    }

    public function test_writeDemandWorkflowRuns_returns_only_write_demand_workflows()
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
            $extractDataWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $writeDemandWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND],
        ]);

        // When
        $writeDemandWorkflows = $uiDemand->writeDemandWorkflowRuns()->get();

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
            $workflow1->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $workflow2->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
        ]);

        // Then
        $this->assertEquals(2, $uiDemand->extractDataWorkflowRuns()->count());
        $this->assertEquals(2, $uiDemand->workflowRuns()->count());
    }

    public function test_getLatestExtractDataWorkflowRun_returns_most_recent_extract_data_workflow()
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
            'created_at' => now()->subHour(),
        ]);

        $newerWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $olderWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $newerWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestExtractDataWorkflowRun();

        // Then
        $this->assertNotNull($latestWorkflow);
        $this->assertEquals($newerWorkflow->id, $latestWorkflow->id);
    }

    public function test_getLatestWriteDemandWorkflowRun_returns_most_recent_write_demand_workflow()
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
            'created_at' => now()->subHour(),
        ]);

        $newerWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $olderWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND],
            $newerWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND],
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestWriteDemandWorkflowRun();

        // Then
        $this->assertNotNull($latestWorkflow);
        $this->assertEquals($newerWorkflow->id, $latestWorkflow->id);
    }

    public function test_getLatestExtractDataWorkflowRun_returns_null_when_no_workflows_exist()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestExtractDataWorkflowRun();

        // Then
        $this->assertNull($latestWorkflow);
    }

    public function test_getLatestWriteDemandWorkflowRun_returns_null_when_no_workflows_exist()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $latestWorkflow = $uiDemand->getLatestWriteDemandWorkflowRun();

        // Then
        $this->assertNull($latestWorkflow);
    }

    public function test_getExtractDataProgress_returns_zero_when_no_extract_data_workflow_exists()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $progress = $uiDemand->getExtractDataProgress();

        // Then
        $this->assertEquals(0, $progress);
    }

    public function test_getExtractDataProgress_returns_100_when_workflow_is_completed()
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
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $progress = $uiDemand->getExtractDataProgress();

        // Then
        $this->assertEquals(100, $progress);
    }

    public function test_getExtractDataProgress_calculates_correct_percentage_for_running_workflows()
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
            'started_at' => now(),
        ]);

        // Create 4 task runs, 2 completed, 1 failed, 1 running
        $completedTask1 = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $completedTask1->save(); // Trigger status computation

        $completedTask2 = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $completedTask2->save(); // Trigger status computation

        $failedTask = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'failed_at' => now(),
        ]);
        $failedTask->save(); // Trigger status computation

        $runningTask = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now(),
        ]);
        $runningTask->save(); // Trigger status computation

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $progress = $uiDemand->getExtractDataProgress();

        // Then - 3 out of 4 tasks are completed/failed (75%)
        $this->assertEquals(75, $progress);
    }

    public function test_getWriteDemandProgress_returns_zero_when_no_write_demand_workflow_exists()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $progress = $uiDemand->getWriteDemandProgress();

        // Then
        $this->assertEquals(0, $progress);
    }

    public function test_getWriteDemandProgress_returns_100_when_workflow_is_completed()
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
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $progress = $uiDemand->getWriteDemandProgress();

        // Then
        $this->assertEquals(100, $progress);
    }

    public function test_getWriteDemandProgress_calculates_correct_percentage_for_running_workflows()
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
            'started_at' => now(),
        ]);

        // Create 3 task runs, 1 completed, 2 running
        $completedTask = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $completedTask->save(); // Trigger status computation

        $runningTask1 = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now(),
        ]);
        $runningTask1->save(); // Trigger status computation

        $runningTask2 = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now(),
        ]);
        $runningTask2->save(); // Trigger status computation

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $progress = $uiDemand->getWriteDemandProgress();

        // Then - 1 out of 3 tasks are completed (33%)
        $this->assertEquals(33, $progress);
    }

    public function test_progress_calculation_with_no_tasks_returns_zero()
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
            'started_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $progress = $uiDemand->getExtractDataProgress();

        // Then
        $this->assertEquals(0, $progress);
    }

    public function test_isExtractDataRunning_returns_true_when_extract_data_workflow_is_running()
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
            'started_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $isRunning = $uiDemand->isExtractDataRunning();

        // Then
        $this->assertTrue($isRunning);
    }

    public function test_isExtractDataRunning_returns_false_when_no_extract_data_workflow_exists()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $isRunning = $uiDemand->isExtractDataRunning();

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_isExtractDataRunning_returns_false_when_extract_data_workflow_is_completed()
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
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $isRunning = $uiDemand->isExtractDataRunning();

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_isWriteDemandRunning_returns_true_when_write_demand_workflow_is_running()
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
            'started_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $isRunning = $uiDemand->isWriteDemandRunning();

        // Then
        $this->assertTrue($isRunning);
    }

    public function test_isWriteDemandRunning_returns_false_when_no_write_demand_workflow_exists()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $isRunning = $uiDemand->isWriteDemandRunning();

        // Then
        $this->assertFalse($isRunning);
    }

    public function test_isWriteDemandRunning_returns_false_when_write_demand_workflow_is_completed()
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
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $isRunning = $uiDemand->isWriteDemandRunning();

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
            'started_at' => now(),
        ]);

        $writeDemandWorkflow = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $writeDemandWorkflow->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND],
        ]);

        // When
        $isExtractDataRunning = $uiDemand->isExtractDataRunning();
        $isWriteDemandRunning = $uiDemand->isWriteDemandRunning();

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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Then
        $attachedWorkflow = $uiDemand->workflowRuns()->first();
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_EXTRACT_DATA, $attachedWorkflow->pivot->workflow_type);
        $this->assertNotNull($attachedWorkflow->pivot->created_at);
        $this->assertNotNull($attachedWorkflow->pivot->updated_at);
    }

    public function test_multiple_workflows_of_same_type_can_exist_on_one_demand()
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
            'created_at' => now()->subHour(),
        ]);

        $workflow2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at' => now(),
        ]);

        // When
        $uiDemand->workflowRuns()->attach([
            $workflow1->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $workflow2->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
        ]);

        // Then
        $this->assertEquals(2, $uiDemand->extractDataWorkflowRuns()->count());
        $latestWorkflow = $uiDemand->getLatestExtractDataWorkflowRun();
        $this->assertEquals($workflow2->id, $latestWorkflow->id);
    }

    public function test_progress_calculation_handles_failed_workflows_properly()
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
            'failed_at' => now(),
        ]);

        // Create task runs with different statuses
        $completedTask = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $completedTask->save(); // Trigger status computation

        $failedTask = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'failed_at' => now(),
        ]);
        $failedTask->save(); // Trigger status computation

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $progress = $uiDemand->getExtractDataProgress();

        // Then - 2 out of 2 tasks are finished (completed/failed), so 100%
        $this->assertEquals(100, $progress);
    }

    public function test_progress_calculation_handles_stopped_workflows_properly()
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
            'stopped_at' => now(),
        ]);

        $completedTask = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $completedTask->save(); // Trigger status computation

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $progress = $uiDemand->getWriteDemandProgress();

        // Then - 1 out of 1 tasks are finished, so 100%
        $this->assertEquals(100, $progress);
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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $isRunning = $uiDemand->isExtractDataRunning();

        // Then
        $this->assertTrue($isRunning);
    }
}