<?php

namespace Tests\Unit\Models;

use App\Models\Demand\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Newms87\Danx\Models\Utilities\StoredFile;
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

    public function test_canExtractData_withValidConditions_returnsTrue(): void
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

        // When & Then
        $this->assertTrue($uiDemand->canExtractData());
    }

    public function test_canExtractData_withCompletedStatus_returnsFalse(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_COMPLETED, // Invalid status
            'title'   => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // When & Then
        $this->assertFalse($uiDemand->canExtractData());
    }

    public function test_canExtractData_withNoInputFiles_returnsFalse(): void
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
        $this->assertFalse($uiDemand->canExtractData());
    }

    public function test_canExtractData_withRunningWorkflow_returnsFalse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $runningWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Running',
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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canExtractData());
    }

    public function test_canWriteMedicalSummary_withValidConditions_returnsTrue(): void
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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When & Then
        $this->assertTrue($uiDemand->canWriteMedicalSummary());
    }

    public function test_canWriteMedicalSummary_withNoTeamObject_returnsFalse(): void
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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canWriteMedicalSummary());
    }

    public function test_canWriteMedicalSummary_withRunningWorkflow_returnsFalse(): void
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

        $runningMedicalSummaryRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Running',
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
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Attach running medical summary workflow
        $uiDemand->workflowRuns()->attach($runningMedicalSummaryRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canWriteMedicalSummary());
    }

    public function test_canWriteMedicalSummary_withoutExtractDataCompleted_returnsFalse(): void
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
        $this->assertFalse($uiDemand->canWriteMedicalSummary());
    }

    public function test_canWriteDemandLetter_withValidConditions_returnsTrue(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $completedMedicalSummaryRun = WorkflowRun::factory()->create([
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

        // Attach completed medical summary workflow
        $uiDemand->workflowRuns()->attach($completedMedicalSummaryRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // When & Then
        $this->assertTrue($uiDemand->canWriteDemandLetter());
    }

    public function test_canWriteDemandLetter_withNoTeamObject_returnsFalse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $completedMedicalSummaryRun = WorkflowRun::factory()->create([
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

        // Attach completed medical summary workflow
        $uiDemand->workflowRuns()->attach($completedMedicalSummaryRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canWriteDemandLetter());
    }

    public function test_canWriteDemandLetter_withRunningWorkflow_returnsFalse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $completedMedicalSummaryRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Completed',
            'completed_at'           => now(),
        ]);

        $runningDemandLetterRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Running',
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

        // Attach completed medical summary workflow
        $uiDemand->workflowRuns()->attach($completedMedicalSummaryRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // Attach running demand letter workflow
        $uiDemand->workflowRuns()->attach($runningDemandLetterRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER,
        ]);

        // When & Then
        $this->assertFalse($uiDemand->canWriteDemandLetter());
    }

    public function test_canWriteDemandLetter_withoutMedicalSummaryCompleted_returnsFalse(): void
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

        // No medical summary workflow attached

        // When & Then
        $this->assertFalse($uiDemand->canWriteDemandLetter());
    }

    public function test_workflowRunsRelationships_workCorrectly(): void
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
            $extractDataRun->id     => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $medicalSummaryRun->id  => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY],
            $demandLetterRun->id    => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER],
        ]);

        // When & Then
        $this->assertEquals(3, $uiDemand->workflowRuns()->count());
        $this->assertEquals(1, $uiDemand->extractDataWorkflowRuns()->count());
        $this->assertEquals(1, $uiDemand->writeMedicalSummaryWorkflowRuns()->count());
        $this->assertEquals(1, $uiDemand->writeDemandLetterWorkflowRuns()->count());

        $this->assertEquals($extractDataRun->id, $uiDemand->getLatestExtractDataWorkflowRun()->id);
        $this->assertEquals($medicalSummaryRun->id, $uiDemand->getLatestWriteMedicalSummaryWorkflowRun()->id);
        $this->assertEquals($demandLetterRun->id, $uiDemand->getLatestWriteDemandLetterWorkflowRun()->id);
    }

    public function test_progressMethods_returnCorrectValues(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Running',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When & Then
        // Running workflow with no task runs will have 0 progress
        $this->assertEquals(0.0, $uiDemand->getExtractDataProgress());
        $this->assertEquals(0.0, $uiDemand->getWriteMedicalSummaryProgress()); // No workflow attached
        $this->assertEquals(0.0, $uiDemand->getWriteDemandLetterProgress()); // No workflow attached
    }

    public function test_isWorkflowRunningMethods_returnCorrectValues(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $runningRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'Running',
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

        // Attach workflows - one running, one completed
        $uiDemand->workflowRuns()->attach([
            $runningRun->id   => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $completedRun->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY],
        ]);

        // When & Then
        $this->assertTrue($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteMedicalSummaryRunning());
        $this->assertFalse($uiDemand->isWriteDemandLetterRunning());
    }

    public function test_workflowConstants_areCorrect(): void
    {
        // Then
        $this->assertEquals('extract_data', UiDemand::WORKFLOW_TYPE_EXTRACT_DATA);
        $this->assertEquals('write_medical_summary', UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY);
        $this->assertEquals('write_demand_letter', UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER);
    }

    public function test_statusConstants_areCorrect(): void
    {
        // Then
        $this->assertEquals('Draft', UiDemand::STATUS_DRAFT);
        $this->assertEquals('Completed', UiDemand::STATUS_COMPLETED);
        $this->assertEquals('Failed', UiDemand::STATUS_FAILED);
    }

    public function test_validate_withValidData_passes(): void
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

    public function test_validate_withInvalidStatus_fails(): void
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

    public function test_validate_withMissingTitle_fails(): void
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