<?php

namespace Tests\Feature;

use App\Jobs\WorkflowStartNodeJob;
use App\Models\Demand\DemandTemplate;
use App\Models\Demand\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWorkflowIntegrationTest extends AuthenticatedTestCase
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

        // Mock queue to prevent actual job dispatching except for WorkflowStartNodeJob
        Queue::fake();
    }

    public function test_complete4StepWorkflow_fromStartToFinish_worksCorrectly(): void
    {
        // Given - Set up all workflow definitions
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

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Complete Workflow Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // Step 1: Extract Data
        $this->assertTrue($uiDemand->canExtractData());
        $this->assertFalse($uiDemand->canWriteMedicalSummary());
        $this->assertFalse($uiDemand->canWriteDemandLetter());

        $extractDataWorkflowRun = $this->service->extractData($uiDemand);
        $this->assertInstanceOf(WorkflowRun::class, $extractDataWorkflowRun);
        $this->assertTrue($uiDemand->fresh()->isExtractDataRunning());
        $this->assertFalse($uiDemand->fresh()->canExtractData()); // Can't start another while running

        // Simulate extract data completion
        $extractDataWorkflowRun->update([
            'completed_at' => now(),
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $uiDemand->update(['team_object_id' => $teamObject->id]);

        $this->service->handleUiDemandWorkflowComplete($extractDataWorkflowRun);

        // Verify extract data completed
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('extract_data_completed_at', $updatedDemand->metadata);
        $this->assertFalse($updatedDemand->isExtractDataRunning());
        $this->assertTrue($updatedDemand->canWriteMedicalSummary());
        $this->assertFalse($updatedDemand->canWriteDemandLetter()); // Still can't until medical summary done

        // Step 2: Write Medical Summary
        $writeMedicalSummaryWorkflowRun = $this->service->writeMedicalSummary($updatedDemand);
        $this->assertInstanceOf(WorkflowRun::class, $writeMedicalSummaryWorkflowRun);
        $this->assertTrue($updatedDemand->fresh()->isWriteMedicalSummaryRunning());
        $this->assertFalse($updatedDemand->fresh()->canWriteMedicalSummary()); // Can't start another while running

        // Simulate medical summary completion
        $writeMedicalSummaryWorkflowRun->update([
            'completed_at' => now(),
        ]);

        $this->service->handleUiDemandWorkflowComplete($writeMedicalSummaryWorkflowRun);

        // Verify medical summary completed
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('write_medical_summary_completed_at', $updatedDemand->metadata);
        $this->assertFalse($updatedDemand->isWriteMedicalSummaryRunning());
        $this->assertTrue($updatedDemand->canWriteDemandLetter()); // Now can write demand letter

        // Step 3: Write Demand Letter
        $writeDemandLetterWorkflowRun = $this->service->writeDemandLetter($updatedDemand);
        $this->assertInstanceOf(WorkflowRun::class, $writeDemandLetterWorkflowRun);
        $this->assertTrue($updatedDemand->fresh()->isWriteDemandLetterRunning());
        $this->assertFalse($updatedDemand->fresh()->canWriteDemandLetter()); // Can't start another while running

        // Simulate demand letter completion
        $writeDemandLetterWorkflowRun->update([
            'completed_at' => now(),
        ]);

        $this->service->handleUiDemandWorkflowComplete($writeDemandLetterWorkflowRun);

        // Verify demand letter completed (Step 4: Complete)
        $finalDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $finalDemand->status);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $finalDemand->metadata);
        $this->assertFalse($finalDemand->isWriteDemandLetterRunning());

        // Verify all workflows are tracked correctly
        $this->assertEquals(3, $finalDemand->workflowRuns()->count());
        $this->assertEquals(1, $finalDemand->extractDataWorkflowRuns()->count());
        $this->assertEquals(1, $finalDemand->writeMedicalSummaryWorkflowRuns()->count());
        $this->assertEquals(1, $finalDemand->writeDemandLetterWorkflowRuns()->count());

        // Verify metadata contains all completion timestamps
        $this->assertArrayHasKey('extract_data_completed_at', $finalDemand->metadata);
        $this->assertArrayHasKey('write_medical_summary_completed_at', $finalDemand->metadata);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $finalDemand->metadata);
    }

    public function test_workflowDependencies_enforcesCorrectOrder(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Dependency Test Demand',
        ]);

        // Should not be able to write medical summary without extract data completed
        $this->assertFalse($uiDemand->canWriteMedicalSummary());

        // Should not be able to write demand letter without medical summary completed
        $this->assertFalse($uiDemand->canWriteDemandLetter());

        // Even if we manually set a team object, still can't write medical summary without extract data completed
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $uiDemand->update(['team_object_id' => $teamObject->id]);
        $this->assertFalse($uiDemand->canWriteMedicalSummary());

        // Add a completed extract data workflow
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Now should be able to write medical summary but not demand letter
        $this->assertTrue($uiDemand->fresh()->canWriteMedicalSummary());
        $this->assertFalse($uiDemand->fresh()->canWriteDemandLetter());

        // Add a completed medical summary workflow
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // Now should be able to write demand letter
        $this->assertTrue($uiDemand->fresh()->canWriteDemandLetter());
    }

    public function test_workflowFailure_handlesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Failure Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // When - Start workflow and simulate failure
        $workflowRun = $this->service->extractData($uiDemand);

        $workflowRun->update([
            'failed_at' => now(),
        ]);

        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify failure handling
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_FAILED, $updatedDemand->status);
        $this->assertArrayHasKey('failed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('error', $updatedDemand->metadata);
        $this->assertEquals('Failed', $updatedDemand->metadata['error']);
    }

    public function test_multipleWorkflowRuns_latestIsUsedForCapabilities(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Multiple Runs Test Demand',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $uiDemand->update(['team_object_id' => $teamObject->id]);

        // Create multiple extract data workflow runs
        $firstRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'failed_at'              => now()->subHour(),
            'created_at'             => now()->subHour(),
        ]);

        $secondRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
            'created_at'             => now(), // Latest
        ]);

        $uiDemand->workflowRuns()->attach([
            $firstRun->id  => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $secondRun->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
        ]);

        // When & Then - Latest (completed) run should determine capability
        $this->assertTrue($uiDemand->canWriteMedicalSummary());
        $this->assertEquals($secondRun->id, $uiDemand->getLatestExtractDataWorkflowRun()->id);
        $this->assertFalse($uiDemand->isExtractDataRunning());
    }

    public function test_workflowWithInstructionTemplate_combinesInstructions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
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
            'title'          => 'Template Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Create instruction template
        $instructionTemplate = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'content' => 'Use formal medical terminology and include specific injury details.',
        ]);

        // When - Test the actual writeMedicalSummary method
        $workflowRun = $this->service->writeMedicalSummary(
            $uiDemand,
            $instructionTemplate->id,
            'Focus on the most severe injuries only.'
        );

        // Execute the WorkflowStartNodeJob to attach input artifacts
        Queue::assertPushed(WorkflowStartNodeJob::class, function ($job) {
            $job->run();

            return true;
        });

        // Then - Test that the workflow task run contains the artifact with instruction content
        $artifact = $workflowRun->taskRuns()->first()->inputArtifacts()->first();
        $this->assertStringContainsStringIgnoringCase('instructions', $artifact->text_content);
        $this->assertStringContainsString('Use formal medical terminology and include specific injury details.', $artifact->text_content);
        $this->assertStringContainsString('Focus on the most severe injuries only.', $artifact->text_content);
    }

    public function test_workflowWithDemandTemplate_includesTemplateData(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
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
            'title'          => 'Template Test Demand',
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflowRun->id    => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY],
        ]);

        // Create demand template with stored file
        $templateFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'demand_template.docx',
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $templateFile->id,
        ]);

        // When - Test the actual writeDemandLetter method
        $workflowRun = $this->service->writeDemandLetter(
            $uiDemand,
            $template->id,
            'Include specific monetary damages and timeline.'
        );

        // Execute the WorkflowStartNodeJob to attach input artifacts
        Queue::assertPushed(WorkflowStartNodeJob::class, function ($job) {
            $job->run();

            return true;
        });

        // Then - Test that the workflow task run contains the artifact with template data
        $artifact    = $workflowRun->taskRuns()->first()->inputArtifacts()->first();
        $contentData = json_decode($artifact->text_content, true);
        $this->assertEquals($templateFile->id, $contentData['template_stored_file_id']);
        $this->assertEquals('Include specific monetary damages and timeline.', $contentData['additional_instructions']);
        $this->assertEquals($uiDemand->id, $contentData['demand_id']);
        $this->assertEquals($uiDemand->title, $contentData['title']);
    }

    public function test_workflowProgress_calculatedCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Progress Test Demand',
        ]);

        // When
        $uiDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Then - Running workflow with no task runs will have 0 progress
        $this->assertEquals(0.0, $uiDemand->getExtractDataProgress());
        $this->assertEquals(0.0, $uiDemand->getWriteMedicalSummaryProgress());
        $this->assertEquals(0.0, $uiDemand->getWriteDemandLetterProgress());
    }

    public function test_teamScopingEnforced_inAllMethods(): void
    {
        // Given - Create demand in different team
        $otherTeam = \App\Models\Team\Team::factory()->create();

        $otherTeamDemand = UiDemand::factory()->create([
            'team_id' => $otherTeam->id,
            'title'   => 'Other Team Demand',
        ]);

        // Current user's team demand
        $currentTeamDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title'   => 'Current Team Demand',
        ]);

        // When & Then - Service methods should work with current team demand
        // but not find workflows from other teams
        $this->assertInstanceOf(UiDemand::class, $currentTeamDemand);

        // Verify team scoping in workflow relationships
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $currentTeamDemand->workflowRuns()->attach($workflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Should find the workflow for current team demand
        $this->assertNotNull($currentTeamDemand->getLatestExtractDataWorkflowRun());

        // Should not find workflows for other team demand (empty relationships)
        $this->assertNull($otherTeamDemand->getLatestExtractDataWorkflowRun());
    }
}
