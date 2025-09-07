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

    protected UiDemandWorkflowService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(UiDemandWorkflowService::class);

        // Set up workflow configuration
        Config::set('ui-demands.workflows.extract_data', 'Extract Service Dates');
        Config::set('ui-demands.workflows.write_medical_summary', 'Write Medical Summary');
        Config::set('ui-demands.workflows.write_demand_letter', 'Write Demand Letter');

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_multiple_write_demand_letter_attempts_blocked_when_workflow_running(): void
    {
        // Given - Set up demand with both extract data and medical summary completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandLetterWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
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

        // Create completed write medical summary workflow run (REQUIRED prerequisite)
        $writeMedicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($writeMedicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // When - Start first write demand letter workflow
        $firstWorkflowRun = $this->service->writeDemandLetter($uiDemand);

        // Verify the workflow is running
        $uiDemand = $uiDemand->fresh();
        $this->assertTrue($uiDemand->isWriteDemandLetterRunning());
        $this->assertFalse($uiDemand->canWriteDemandLetter());

        // Then - Second attempt should fail
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand letter. Check if write medical summary is completed and team object exists.');

        // When - Try to start another write demand letter workflow
        $this->service->writeDemandLetter($uiDemand);
    }

    public function test_write_demand_letter_can_be_run_multiple_times_after_completion(): void
    {
        // Given - Set up demand with both extract data and medical summary completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandLetterWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
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

        // Create completed write medical summary workflow run (REQUIRED prerequisite)
        $writeMedicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($writeMedicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // When - Start first write demand letter workflow
        $firstWorkflowRun = $this->service->writeDemandLetter($uiDemand);

        // Simulate workflow completion
        $firstWorkflowRun->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Handle workflow completion to update demand status
        $this->service->handleUiDemandWorkflowComplete($firstWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // Verify demand stays as DRAFT and can still start write demand letter workflows
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertTrue($uiDemand->canWriteDemandLetter());

        // When - Start another write demand letter workflow (should succeed now)
        $secondWorkflowRun = $this->service->writeDemandLetter($uiDemand);

        // Then - Should successfully create second workflow run
        $this->assertInstanceOf(WorkflowRun::class, $secondWorkflowRun);
        $this->assertNotEquals($firstWorkflowRun->id, $secondWorkflowRun->id);
    }

    public function test_write_demand_letter_can_be_retried_after_failure(): void
    {
        // Given - Set up demand with both extract data and medical summary completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandLetterWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
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

        // Create completed write medical summary workflow run (REQUIRED prerequisite)
        $writeMedicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($writeMedicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // When - Start first write demand letter workflow
        $firstWorkflowRun = $this->service->writeDemandLetter($uiDemand);

        // Simulate workflow failure
        $firstWorkflowRun->update([
            'status'    => 'failed',
            'failed_at' => now(),
        ]);

        // Handle workflow completion to update demand status
        $this->service->handleUiDemandWorkflowComplete($firstWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // Verify demand is failed but can still start write demand letter workflows (retry capability)
        $this->assertEquals(UiDemand::STATUS_FAILED, $uiDemand->status);
        $this->assertTrue($uiDemand->canWriteDemandLetter());

        // When - Start another write demand letter workflow (retry after failure)
        $secondWorkflowRun = $this->service->writeDemandLetter($uiDemand);

        // Then - Should successfully create second workflow run for retry
        $this->assertInstanceOf(WorkflowRun::class, $secondWorkflowRun);
        $this->assertNotEquals($firstWorkflowRun->id, $secondWorkflowRun->id);
    }

    public function test_write_demand_letter_without_team_object_relationship_loaded(): void
    {
        // Given - Create demand without loading teamObject relationship
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandLetterWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
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

        // Create completed write medical summary workflow run (REQUIRED prerequisite)
        $writeMedicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($writeMedicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // Verify teamObject relationship is not loaded
        $this->assertFalse($uiDemand->relationLoaded('teamObject'));

        // When - Start write demand letter workflow (should work despite unloaded relationship)
        $workflowRun = $this->service->writeDemandLetter($uiDemand);

        // Then - Should successfully create workflow run
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
    }

    public function test_concurrent_extract_data_and_write_demand_letter_workflows(): void
    {
        // Given - Set up all workflow definitions
        $extractDataWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandLetterWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
            'title'          => 'Test Concurrent Workflows',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflow->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Create completed write medical summary workflow run (REQUIRED for write demand letter)
        $writeMedicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflow->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($writeMedicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // When - Start write demand letter workflow first
        $writeWorkflowRun = $this->service->writeDemandLetter($uiDemand);
        $uiDemand = $uiDemand->fresh();

        // Verify write demand letter is running
        $this->assertTrue($uiDemand->isWriteDemandLetterRunning());
        $this->assertFalse($uiDemand->canWriteDemandLetter());

        // Should still be able to start extract data workflow (separate workflow type)
        $this->assertTrue($uiDemand->canExtractData());

        // When - Start extract data workflow while write demand letter is running
        $extractWorkflowRun = $this->service->extractData($uiDemand);

        // Then - Both workflows should exist
        $uiDemand = $uiDemand->fresh();
        $this->assertTrue($uiDemand->isExtractDataRunning());
        $this->assertTrue($uiDemand->isWriteDemandLetterRunning());
        $this->assertFalse($uiDemand->canExtractData());
        $this->assertFalse($uiDemand->canWriteDemandLetter());

        // Verify both workflow runs are tracked
        $this->assertTrue($uiDemand->workflowRuns()->where('workflow_runs.id', $writeWorkflowRun->id)->exists());
        $this->assertTrue($uiDemand->workflowRuns()->where('workflow_runs.id', $extractWorkflowRun->id)->exists());
    }
}