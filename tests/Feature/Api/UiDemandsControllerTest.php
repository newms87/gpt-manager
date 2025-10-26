<?php

namespace Tests\Feature\Api;

use App\Models\Demand\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandsControllerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Set up workflow configuration
        Config::set('ui-demands.workflows.extract_data', 'Extract Service Dates');
        Config::set('ui-demands.workflows.write_medical_summary', 'Write Medical Summary');
        Config::set('ui-demands.workflows.write_demand_letter', 'Write Demand Letter');

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_extractData_withValidRequest_returnsSuccessResponse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
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

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/extract-data");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'can_extract_data',
            'can_write_medical_summary',
            'can_write_demand_letter',
            'is_extract_data_running',
            'extract_data_workflow_run' => [
                'id',
                'status',
                'progress_percent',
            ],
            'write_medical_summary_workflow_run',
            'write_demand_letter_workflow_run',
        ]);

        // Verify workflow was started
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->exists());
        $this->assertTrue($uiDemand->fresh()->isExtractDataRunning());
    }

    public function test_extractData_withInvalidDemand_returns400Error(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_COMPLETED, // Invalid status
            'title'   => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/extract-data");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
    }

    public function test_writeMedicalSummary_withValidRequest_returnsSuccessResponse(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
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
            'title'          => 'Test Demand',
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

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-medical-summary");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'can_write_medical_summary',
            'is_write_medical_summary_running',
            'write_medical_summary_workflow_run' => [
                'id',
                'status',
                'progress_percent',
            ],
        ]);

        // Verify workflow was started
        $workflowRuns = $uiDemand->fresh()->workflowRuns;
        $this->assertTrue($workflowRuns->count() > 0);
        $this->assertTrue($uiDemand->fresh()->isWriteMedicalSummaryRunning());
    }

    public function test_writeMedicalSummary_withInvalidDemand_returns400Error(): void
    {
        // Given - demand without team object
        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title'          => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-medical-summary");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
        $response->assertJsonFragment([
            'message' => 'Failed to start write medical summary workflow.',
        ]);
    }

    public function test_writeMedicalSummary_withoutExtractDataCompleted_returns400Error(): void
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
            'metadata'       => [], // No extract data completed metadata
            'title'          => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-medical-summary");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
    }

    public function test_extractData_endpoint_loadsCorrectRelationships(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
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

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/extract-data");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('input_files', $data);
        $this->assertArrayHasKey('output_files', $data);
        $this->assertArrayHasKey('extract_data_workflow_run', $data);

        // Verify workflow run has necessary data
        $this->assertNotNull($data['extract_data_workflow_run']);
        $this->assertArrayHasKey('progress_percent', $data['extract_data_workflow_run']);
        $this->assertArrayHasKey('total_nodes', $data['extract_data_workflow_run']);
        $this->assertArrayHasKey('completed_tasks', $data['extract_data_workflow_run']);
    }

    public function test_writeMedicalSummary_endpoint_loadsCorrectRelationships(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
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
            'title'          => 'Test Demand',
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

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-medical-summary");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('write_medical_summary_workflow_run', $data);
        $this->assertArrayHasKey('write_demand_letter_workflow_run', $data);

        // Verify workflow run has necessary data
        $this->assertNotNull($data['write_medical_summary_workflow_run']);
        $this->assertArrayHasKey('progress_percent', $data['write_medical_summary_workflow_run']);
        $this->assertArrayHasKey('total_nodes', $data['write_medical_summary_workflow_run']);
        $this->assertArrayHasKey('completed_tasks', $data['write_medical_summary_workflow_run']);
    }

    public function test_demand_canWriteDemand_flagIsCorrect(): void
    {
        // Given - fresh demand without extract data completed
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => [],
            'title'          => 'Test Demand',
        ]);

        // When - get demand details
        $response = $this->getJson("/api/ui-demands/{$uiDemand->id}/details");

        // Then - can_write_medical_summary should be false
        $response->assertSuccessful();
        $data = $response->json();
        $this->assertFalse($data['can_write_medical_summary']);

        // Now complete extract data and verify can_write_medical_summary becomes true
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        $uiDemand->update([
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
        ]);

        $response = $this->getJson("/api/ui-demands/{$uiDemand->id}/details");
        $response->assertSuccessful();
        $data = $response->json();
        $this->assertTrue($data['can_write_medical_summary']);
    }

    public function test_workflow_completion_updates_canWriteMedicalSummary_correctly(): void
    {
        // Given - Set up extract data workflow that will complete
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
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
            'metadata'       => [],
            'title'          => 'Test Demand',
        ]);

        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        // Should NOT be able to write demand letter yet - need medical summary first
        $this->assertFalse($uiDemand->canWriteDemandLetter());

        // But should be able to write medical summary since extract data is completed
        $this->assertTrue($uiDemand->canWriteMedicalSummary());

        // When - Handle workflow completion
        $service = app(UiDemandWorkflowService::class);
        $service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Should now be able to write medical summary (metadata updated)
        $updatedDemand = $uiDemand->fresh();
        $this->assertTrue($updatedDemand->canWriteMedicalSummary());
        $this->assertArrayHasKey('extract_data_completed_at', $updatedDemand->metadata);
    }

    public function test_writeDemandLetter_withValidRequest_returnsSuccessResponse(): void
    {
        // Given
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
            'title'          => 'Test Demand',
        ]);

        // Create completed extract data and medical summary workflow runs
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflowRun->id    => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY],
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand-letter");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'can_write_demand_letter',
            'is_write_demand_letter_running',
            'write_demand_letter_workflow_run' => [
                'id',
                'status',
                'progress_percent',
            ],
        ]);

        // Verify workflow was started
        $workflowRuns = $uiDemand->fresh()->workflowRuns;
        $this->assertTrue($workflowRuns->count() > 0);
        $this->assertTrue($uiDemand->fresh()->isWriteDemandLetterRunning());
    }

    public function test_writeDemandLetter_withInvalidDemand_returns400Error(): void
    {
        // Given - demand without team object
        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title'          => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand-letter");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
        $response->assertJsonFragment([
            'message' => 'Failed to start write demand letter workflow.',
        ]);
    }

    public function test_writeDemandLetter_withoutMedicalSummaryCompleted_returns400Error(): void
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
            'metadata'       => [], // No medical summary completed metadata
            'title'          => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand-letter");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
    }

    public function test_writeDemandLetter_endpoint_loadsCorrectRelationships(): void
    {
        // Given
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
            'title'          => 'Test Demand',
        ]);

        // Create completed workflows
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflowRun->id    => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY],
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand-letter");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('write_demand_letter_workflow_run', $data);

        // Verify workflow run has necessary data
        $this->assertNotNull($data['write_demand_letter_workflow_run']);
        $this->assertArrayHasKey('progress_percent', $data['write_demand_letter_workflow_run']);
        $this->assertArrayHasKey('total_nodes', $data['write_demand_letter_workflow_run']);
        $this->assertArrayHasKey('completed_tasks', $data['write_demand_letter_workflow_run']);
    }

    public function test_writeMedicalSummary_withInstructionTemplate_acceptsTemplateId(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeMedicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
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
            'title'          => 'Test Demand',
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

        // Create instruction template
        $instructionTemplate = \App\Models\Workflow\WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-medical-summary", [
            'instruction_template_id'  => $instructionTemplate->id,
            'additional_instructions'  => 'Focus on injury details',
        ]);

        // Then
        $response->assertSuccessful();
        $this->assertTrue($uiDemand->fresh()->isWriteMedicalSummaryRunning());
    }

    public function test_writeDemandLetter_withTemplate_acceptsTemplateId(): void
    {
        // Given
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
            'title'          => 'Test Demand',
        ]);

        // Create completed workflows
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach([
            $extractDataWorkflowRun->id    => ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY],
        ]);

        // Create template
        $template = \App\Models\Demand\DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand-letter", [
            'template_id'             => $template->id,
            'additional_instructions' => 'Include specific damages',
        ]);

        // Then
        $response->assertSuccessful();
        $this->assertTrue($uiDemand->fresh()->isWriteDemandLetterRunning());
    }
}
