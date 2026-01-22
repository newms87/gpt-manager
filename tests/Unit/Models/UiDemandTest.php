<?php

namespace Tests\Unit\Models;

use App\Models\Demand\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    /**
     * Test canRunWorkflow with valid conditions for extract_data
     */
    #[Test]
    public function canRunWorkflow_withValidConditionsForExtractData_returnsTrue(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // Complete organize_files workflow first (dependency for extract_data)
        $organizeFilesWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $organizeFilesWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $organizeFilesWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($organizeFilesWorkflowRun->id, [
            'workflow_type' => 'organize_files',
        ]);

        // When & Then
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data'));
    }

    /**
     * Test canRunWorkflow returns false when input files are missing
     */
    #[Test]
    public function canRunWorkflow_withNoInputFiles_returnsFalse(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test Demand',
        ]);

        // No input files attached

        // When & Then
        $this->assertFalse($uiDemand->canRunWorkflow('extract_data'));
    }

    /**
     * Test canRunWorkflow returns false when workflow is already running
     */
    #[Test]
    public function canRunWorkflow_withRunningWorkflow_returnsFalse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $runningWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // Attach running workflow
        $uiDemand->workflowRuns()->attach($runningWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canRunWorkflow('extract_data'));
    }

    /**
     * Test canRunWorkflow with valid conditions for write_medical_summary
     */
    #[Test]
    public function canRunWorkflow_withValidConditionsForWriteMedicalSummary_returnsTrue(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $completedWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Completed',
            'completed_at'           => now(),
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'title'          => 'Test Demand',
        ]);

        // Attach completed extract data workflow
        $uiDemand->workflowRuns()->attach($completedWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When & Then
        $this->assertTrue($uiDemand->canRunWorkflow('write_medical_summary'));
    }

    /**
     * Test canRunWorkflow returns false when dependency is not completed
     */
    #[Test]
    public function canRunWorkflow_withoutDependencyCompleted_returnsFalse(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'title'          => 'Test Demand',
        ]);

        // No extract data workflow attached

        // When & Then
        $this->assertFalse($uiDemand->canRunWorkflow('write_medical_summary'));
    }

    /**
     * Test canRunWorkflow returns false when team_object is missing for team_object source
     */
    #[Test]
    public function canRunWorkflow_withNoTeamObject_returnsFalse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $completedWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Completed',
            'completed_at'           => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title'          => 'Test Demand',
        ]);

        // Attach completed extract data workflow
        $uiDemand->workflowRuns()->attach($completedWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canRunWorkflow('write_medical_summary'));
    }

    /**
     * Test isWorkflowRunning returns true when workflow is running
     */
    #[Test]
    public function isWorkflowRunning_withRunningWorkflow_returnsTrue(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $runningRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach($runningRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When & Then
        $this->assertTrue($uiDemand->isWorkflowRunning('extract_data'));
    }

    /**
     * Test isWorkflowRunning returns false when workflow is completed
     */
    #[Test]
    public function isWorkflowRunning_withCompletedWorkflow_returnsFalse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $completedRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Completed',
            'completed_at'           => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach($completedRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When & Then
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
    }

    /**
     * Test isWorkflowRunning returns false when no workflow is attached
     */
    #[Test]
    public function isWorkflowRunning_withNoWorkflow_returnsFalse(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        // When & Then
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
    }

    /**
     * Test getLatestWorkflowRun returns the latest workflow run
     */
    #[Test]
    public function getLatestWorkflowRun_withMultipleRuns_returnsLatest(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $olderRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at'             => now()->subHours(2),
        ]);

        $newerRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'created_at'             => now()->subHour(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach([
            $olderRun->id => ['workflow_type' => 'extract_data'],
            $newerRun->id => ['workflow_type' => 'extract_data'],
        ]);

        // When
        $latestRun = $uiDemand->getLatestWorkflowRun('extract_data');

        // Then
        $this->assertEquals($newerRun->id, $latestRun->id);
    }

    /**
     * Test getLatestWorkflowRun returns null when no workflow is attached
     */
    #[Test]
    public function getLatestWorkflowRun_withNoWorkflow_returnsNull(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        // When
        $latestRun = $uiDemand->getLatestWorkflowRun('extract_data');

        // Then
        $this->assertNull($latestRun);
    }

    /**
     * Test getLatestWorkflowRun uses preloaded relationships when available
     */
    #[Test]
    public function getLatestWorkflowRun_withPreloadedRelationships_usesPreloaded(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // Load relationship
        $uiDemand->load('workflowRuns');

        // When
        $latestRun = $uiDemand->getLatestWorkflowRun('extract_data');

        // Then
        $this->assertEquals($workflowRun->id, $latestRun->id);
    }

    /**
     * Test workflowRuns relationship works correctly
     */
    #[Test]
    public function workflowRuns_withMultipleWorkflows_returnsAllWorkflows(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $medicalSummaryRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $demandLetterRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        // Attach workflows with different types
        $uiDemand->workflowRuns()->attach([
            $extractDataRun->id    => ['workflow_type' => 'extract_data'],
            $medicalSummaryRun->id => ['workflow_type' => 'write_medical_summary'],
            $demandLetterRun->id   => ['workflow_type' => 'write_demand_letter'],
        ]);

        // When & Then
        $this->assertEquals(3, $uiDemand->workflowRuns()->count());
        $this->assertEquals($extractDataRun->id, $uiDemand->getLatestWorkflowRun('extract_data')->id);
        $this->assertEquals($medicalSummaryRun->id, $uiDemand->getLatestWorkflowRun('write_medical_summary')->id);
        $this->assertEquals($demandLetterRun->id, $uiDemand->getLatestWorkflowRun('write_demand_letter')->id);
    }

    /**
     * Test status constants are correct
     */
    #[Test]
    public function statusConstants_areCorrect(): void
    {
        // Then
        $this->assertEquals('Draft', UiDemand::STATUS_DRAFT);
        $this->assertEquals('Completed', UiDemand::STATUS_COMPLETED);
        $this->assertEquals('Failed', UiDemand::STATUS_FAILED);
    }

    /**
     * Test validate with valid data passes
     */
    #[Test]
    public function validate_withValidData_passes(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $validator = $uiDemand->validate();

        // Then
        $this->assertTrue($validator->passes());
    }

    /**
     * Test validate with invalid status fails
     */
    #[Test]
    public function validate_withInvalidStatus_fails(): void
    {
        // Given
        $data = [
            'title'       => 'Test Demand',
            'description' => 'Test Description',
            'status'      => 'InvalidStatus', // Invalid status
        ];

        $uiDemand = new UiDemand();

        // When
        $validator = $uiDemand->validate($data);

        // Then
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    /**
     * Test validate with missing title fails
     */
    #[Test]
    public function validate_withMissingTitle_fails(): void
    {
        // Given
        $data = [
            'description' => 'Test Description',
            'status'      => UiDemand::STATUS_DRAFT,
            // Missing title
        ];

        $uiDemand = new UiDemand();

        // When
        $validator = $uiDemand->validate($data);

        // Then
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }
}
