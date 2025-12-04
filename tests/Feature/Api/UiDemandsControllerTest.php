<?php

namespace Tests\Feature\Api;

use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
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
        $organizeFilesWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Organize Files',
        ]);

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

        // Create completed organize_files workflow (required dependency for extract_data)
        $organizeFilesWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $organizeFilesWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($organizeFilesWorkflowRun->id, [
            'workflow_type' => 'organize_files',
        ]);

        // Create artifact with organized_file category for extract_data workflow to use
        $organizedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $uiDemand->artifacts()->attach($organizedArtifact->id, ['category' => 'organized_file']);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/extract_data");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'workflow_runs' => [
                'extract_data' => [
                    '*' => [
                        'id',
                        'status',
                        'progress_percent',
                    ],
                ],
            ],
            'workflow_config',
        ]);

        // Verify workflow was started
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->exists());
        $this->assertTrue($uiDemand->fresh()->isWorkflowRunning('extract_data'));
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
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/extract_data");

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
            'workflow_type' => 'extract_data',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_medical_summary");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'workflow_runs' => [
                'write_medical_summary' => [
                    '*' => [
                        'id',
                        'status',
                        'progress_percent',
                    ],
                ],
            ],
            'workflow_config',
        ]);

        // Verify workflow was started
        $workflowRuns = $uiDemand->fresh()->workflowRuns;
        $this->assertTrue($workflowRuns->count() > 0);
        $this->assertTrue($uiDemand->fresh()->isWorkflowRunning('write_medical_summary'));
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
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_medical_summary");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
        $response->assertJsonFragment([
            'message' => "Failed to start workflow 'write_medical_summary'.",
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
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_medical_summary");

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
        $organizeFilesWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Organize Files',
        ]);

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

        // Create completed organize_files workflow (required dependency for extract_data)
        $organizeFilesWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $organizeFilesWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($organizeFilesWorkflowRun->id, [
            'workflow_type' => 'organize_files',
        ]);

        // Create artifact with organized_file category for extract_data workflow to use
        $organizedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $uiDemand->artifacts()->attach($organizedArtifact->id, ['category' => 'organized_file']);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/extract_data");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('input_files', $data);
        $this->assertArrayHasKey('output_files', $data);
        $this->assertArrayHasKey('workflow_runs', $data);

        // Verify workflow run has necessary data (workflow_runs is now an array for each type)
        $this->assertIsArray($data['workflow_runs']['extract_data']);
        $this->assertNotEmpty($data['workflow_runs']['extract_data']);
        $this->assertArrayHasKey('progress_percent', $data['workflow_runs']['extract_data'][0]);
        $this->assertArrayHasKey('total_nodes', $data['workflow_runs']['extract_data'][0]);
        $this->assertArrayHasKey('completed_tasks', $data['workflow_runs']['extract_data'][0]);
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
            'workflow_type' => 'extract_data',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_medical_summary");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('workflow_runs', $data);

        // Verify workflow run has necessary data (workflow_runs is now an array for each type)
        $this->assertIsArray($data['workflow_runs']['write_medical_summary']);
        $this->assertNotEmpty($data['workflow_runs']['write_medical_summary']);
        $this->assertArrayHasKey('progress_percent', $data['workflow_runs']['write_medical_summary'][0]);
        $this->assertArrayHasKey('total_nodes', $data['workflow_runs']['write_medical_summary'][0]);
        $this->assertArrayHasKey('completed_tasks', $data['workflow_runs']['write_medical_summary'][0]);
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

        // Then - should not be able to write medical summary yet (no extract_data completed)
        $this->assertFalse($uiDemand->canRunWorkflow('write_medical_summary'));

        // Now complete extract data and verify can run becomes true
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
            'workflow_type' => 'extract_data',
        ]);

        $uiDemand->update([
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
        ]);

        // Verify can now run write_medical_summary
        $updatedDemand = $uiDemand->fresh();
        $this->assertTrue($updatedDemand->canRunWorkflow('write_medical_summary'));
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
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'extract_data']);

        // Should NOT be able to write demand letter yet - need medical summary first
        $this->assertFalse($uiDemand->canRunWorkflow('write_demand_letter'));

        // But should be able to write medical summary since extract data is completed
        $this->assertTrue($uiDemand->canRunWorkflow('write_medical_summary'));

        // When - Handle workflow completion
        $service = app(UiDemandWorkflowService::class);
        $service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Should now be able to write medical summary (metadata updated)
        $updatedDemand = $uiDemand->fresh();
        $this->assertTrue($updatedDemand->canRunWorkflow('write_medical_summary'));
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
            $extractDataWorkflowRun->id    => ['workflow_type' => 'extract_data'],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => 'write_medical_summary'],
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_demand_letter");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'workflow_runs' => [
                'write_demand_letter' => [
                    '*' => [
                        'id',
                        'status',
                        'progress_percent',
                    ],
                ],
            ],
            'workflow_config',
        ]);

        // Verify workflow was started
        $workflowRuns = $uiDemand->fresh()->workflowRuns;
        $this->assertTrue($workflowRuns->count() > 0);
        $this->assertTrue($uiDemand->fresh()->isWorkflowRunning('write_demand_letter'));
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
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_demand_letter");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
        $response->assertJsonFragment([
            'message' => "Failed to start workflow 'write_demand_letter'.",
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
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_demand_letter");

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
            $extractDataWorkflowRun->id    => ['workflow_type' => 'extract_data'],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => 'write_medical_summary'],
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_demand_letter");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('workflow_runs', $data);

        // Verify workflow run has necessary data (workflow_runs is now an array for each type)
        $this->assertIsArray($data['workflow_runs']['write_demand_letter']);
        $this->assertNotEmpty($data['workflow_runs']['write_demand_letter']);
        $this->assertArrayHasKey('progress_percent', $data['workflow_runs']['write_demand_letter'][0]);
        $this->assertArrayHasKey('total_nodes', $data['workflow_runs']['write_demand_letter'][0]);
        $this->assertArrayHasKey('completed_tasks', $data['workflow_runs']['write_demand_letter'][0]);
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
            'workflow_type' => 'extract_data',
        ]);

        // Create instruction template
        $instructionTemplate = \App\Models\Workflow\WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_medical_summary", [
            'instruction_template_id'  => $instructionTemplate->id,
            'additional_instructions'  => 'Focus on injury details',
        ]);

        // Then
        $response->assertSuccessful();
        $this->assertTrue($uiDemand->fresh()->isWorkflowRunning('write_medical_summary'));
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
            $extractDataWorkflowRun->id    => ['workflow_type' => 'extract_data'],
            $medicalSummaryWorkflowRun->id => ['workflow_type' => 'write_medical_summary'],
        ]);

        // Create template
        $template = \App\Models\Demand\DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/workflow/write_demand_letter", [
            'template_id'             => $template->id,
            'additional_instructions' => 'Include specific damages',
        ]);

        // Then
        $response->assertSuccessful();
        $this->assertTrue($uiDemand->fresh()->isWorkflowRunning('write_demand_letter'));
    }
}
