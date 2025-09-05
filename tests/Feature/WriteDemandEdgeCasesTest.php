<?php

namespace Tests\Feature;

use App\Models\Demand\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WriteDemandEdgeCasesTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Set up workflow configuration
        Config::set('ui-demands.workflows.extract_data', 'Extract Service Dates');
        Config::set('ui-demands.workflows.write_demand', 'Write Demand Summary');

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_multiple_write_demand_attempts_blocked_when_workflow_running(): void
    {
        // Given - Set up demand with extract data completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Test Concurrent Demand',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        $service = app(UiDemandWorkflowService::class);

        // When - Start first write demand workflow
        $firstWorkflowRun = $service->writeDemand($uiDemand);

        // Verify the workflow is running
        $uiDemand = $uiDemand->fresh();
        $this->assertTrue($uiDemand->isWriteDemandRunning());
        $this->assertFalse($uiDemand->canWriteDemand());

        // Then - Second attempt should fail
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand. Check if extract data is completed and team object exists.');

        // When - Try to start another write demand workflow
        $service->writeDemand($uiDemand);
    }

    public function test_write_demand_can_be_run_multiple_times_after_completion(): void
    {
        // Given - Set up demand with extract data completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Test Repeated Demand',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        $service = app(UiDemandWorkflowService::class);

        // When - Start first write demand workflow
        $firstWorkflowRun = $service->writeDemand($uiDemand);

        // Simulate workflow completion
        $firstWorkflowRun->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Handle workflow completion to update demand status
        $service->handleUiDemandWorkflowComplete($firstWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // Verify demand stays as DRAFT and can still start write demand workflows
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertTrue($uiDemand->canWriteDemand());

        // When - Start another write demand workflow (should succeed now)
        $secondWorkflowRun = $service->writeDemand($uiDemand);

        // Then - Should successfully create second workflow run
        $this->assertInstanceOf(WorkflowRun::class, $secondWorkflowRun);
        $this->assertNotEquals($firstWorkflowRun->id, $secondWorkflowRun->id);
    }

    public function test_write_demand_can_be_retried_after_failure(): void
    {
        // Given - Set up demand with extract data completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Test Failed Workflow Retry',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        $service = app(UiDemandWorkflowService::class);

        // When - Start first write demand workflow
        $firstWorkflowRun = $service->writeDemand($uiDemand);

        // Simulate workflow failure
        $firstWorkflowRun->update([
            'status'    => 'failed',
            'failed_at' => now(),
        ]);

        // Handle workflow completion to update demand status
        $service->handleUiDemandWorkflowComplete($firstWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // Verify demand is failed but can still start write demand workflows (retry capability)
        $this->assertEquals(UiDemand::STATUS_FAILED, $uiDemand->status);
        $this->assertTrue($uiDemand->canWriteDemand());

        // When - Start another write demand workflow (retry after failure)
        $secondWorkflowRun = $service->writeDemand($uiDemand);

        // Then - Should successfully create second workflow run for retry
        $this->assertInstanceOf(WorkflowRun::class, $secondWorkflowRun);
        $this->assertNotEquals($firstWorkflowRun->id, $secondWorkflowRun->id);
    }

    public function test_write_demand_without_team_object_relationship_loaded(): void
    {
        // Given - Create demand without loading teamObject relationship
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Test Unloaded TeamObject',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Verify teamObject relationship is not loaded
        $this->assertFalse($uiDemand->relationLoaded('teamObject'));

        $service = app(UiDemandWorkflowService::class);

        // When - Start write demand workflow (should work despite unloaded relationship)
        $workflowRun = $service->writeDemand($uiDemand);

        // Then - Should successfully create workflow run
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
    }

    public function test_concurrent_extract_data_and_write_demand_workflows(): void
    {
        // Given - Set up both workflow definitions
        $extractDataWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'title'          => 'Test Concurrent Workflows',
        ]);

        // Create completed extract data workflow run so write demand can start
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflow->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        $service = app(UiDemandWorkflowService::class);

        // When - Start write demand workflow first
        $writeWorkflowRun = $service->writeDemand($uiDemand);
        $uiDemand         = $uiDemand->fresh();

        // Verify write demand is running
        $this->assertTrue($uiDemand->isWriteDemandRunning());
        $this->assertFalse($uiDemand->canWriteDemand());

        // Should still be able to start extract data workflow (separate workflow type)
        $this->assertTrue($uiDemand->canExtractData());

        // When - Start extract data workflow while write demand is running
        $extractWorkflowRun = $service->extractData($uiDemand);

        // Then - Both workflows should exist
        $uiDemand = $uiDemand->fresh();
        $this->assertTrue($uiDemand->isExtractDataRunning());
        $this->assertTrue($uiDemand->isWriteDemandRunning());
        $this->assertFalse($uiDemand->canExtractData());
        $this->assertFalse($uiDemand->canWriteDemand());

        // Verify both workflow runs are tracked
        $this->assertTrue($uiDemand->workflowRuns()->where('workflow_runs.id', $writeWorkflowRun->id)->exists());
        $this->assertTrue($uiDemand->workflowRuns()->where('workflow_runs.id', $extractWorkflowRun->id)->exists());
    }
}
