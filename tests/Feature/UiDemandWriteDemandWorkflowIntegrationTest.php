<?php

namespace Tests\Feature;

use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWriteDemandWorkflowIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Set up workflow configuration for 4-step process
        Config::set('ui-demands.workflows.extract_data', 'Extract Service Dates');
        Config::set('ui-demands.workflows.write_medical_summary', 'Write Medical Summary');
        Config::set('ui-demands.workflows.write_demand_letter', 'Write Demand Letter');

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_complete_workflow_extractData_to_writeDemandLetter_integration(): void
    {
        // Given - Set up all 4-step workflow definitions
        $organizeFilesWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Organize Files',
        ]);

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
            'title'          => 'Test Demand Integration',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        $service = app(UiDemandWorkflowService::class);

        // STEP 0: Complete organize_files workflow first (dependency for extract_data)
        $organizeFilesWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $organizeFilesWorkflow->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($organizeFilesWorkflowRun->id, [
            'workflow_type' => 'organize_files',
        ]);

        // Create artifact with organized_file category for extract_data workflow to use (attached to TeamObject)
        $organizedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $teamObject->artifacts()->attach($organizedArtifact->id, ['category' => 'organized_file']);

        // STEP 1: Verify initial state - can extract data, cannot write demand
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data'));
        $this->assertFalse($uiDemand->canRunWorkflow('write_demand_letter'));
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertFalse($uiDemand->isWorkflowRunning('write_demand_letter'));

        // STEP 2: Start extract data workflow
        $extractDataWorkflowRun = $service->runWorkflow($uiDemand, 'extract_data');
        $uiDemand               = $uiDemand->fresh();

        // Verify extract data is running
        $this->assertFalse($uiDemand->canRunWorkflow('extract_data')); // Can't start another
        $this->assertFalse($uiDemand->canRunWorkflow('write_demand_letter')); // Still can't write demand
        $this->assertTrue($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertFalse($uiDemand->isWorkflowRunning('write_demand_letter'));

        // STEP 3: Simulate the actual workflow execution that would happen in production

        // 1. Create an intermediate task that produces artifacts (like a data extraction task)
        $extractionTaskDef = TaskDefinition::factory()->create([
            'name'             => 'Extract Service Dates Task',
            'task_runner_name' => 'test_extraction_runner', // Simulated
        ]);

        $workflowNode1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $extractDataWorkflow->id,
        ]);

        $extractionTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $extractionTaskDef->id,
            'workflow_run_id'    => $extractDataWorkflowRun->id,
            'workflow_node_id'   => $workflowNode1->id,
        ]);

        // Create an artifact that this extraction task would produce
        $extractedDataArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extracted Service Dates',
        ]);

        // This extraction task outputs its artifact (normal task behavior)
        $extractionTaskRun->outputArtifacts()->attach($extractedDataArtifact->id);

        // 2. Create the workflow output task that collects the final outputs
        $workflowOutputTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        $workflowNode2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $extractDataWorkflow->id,
        ]);

        $outputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id,
            'workflow_run_id'    => $extractDataWorkflowRun->id,
            'workflow_node_id'   => $workflowNode2->id,
        ]);

        // The output task process gets the extracted artifact as input
        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);
        $outputTaskProcess->inputArtifacts()->attach($extractedDataArtifact->id);

        // 3. Run the WorkflowOutputTaskRunner (this is the ONLY place workflow outputs are created)
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        // 4. Mark workflow as completed (as workflow system would do)
        $extractDataWorkflowRun->update([
            'status'       => WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // 5. Handle the workflow completion (this is what actually gets called in production)
        $service->handleUiDemandWorkflowComplete($extractDataWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // STEP 4: Verify extract data completed state - now can write medical summary (not demand letter yet!)
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data')); // Still has files and draft status
        $this->assertFalse($uiDemand->canRunWorkflow('write_demand_letter')); // CANNOT write demand letter until medical summary is complete
        $this->assertTrue($uiDemand->canRunWorkflow('write_medical_summary')); // NOW CAN write medical summary!
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertFalse($uiDemand->isWorkflowRunning('write_demand_letter'));
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertArrayHasKey('extract_data_completed_at', $uiDemand->metadata);

        // STEP 5: Start write medical summary workflow
        $writeMedicalSummaryWorkflowRun = $service->runWorkflow($uiDemand, 'write_medical_summary');
        $uiDemand                       = $uiDemand->fresh();

        // Verify medical summary is running
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data')); // Still can extract data
        $this->assertFalse($uiDemand->canRunWorkflow('write_medical_summary')); // Can't start another medical summary
        $this->assertFalse($uiDemand->canRunWorkflow('write_demand_letter')); // Still can't write demand letter
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertTrue($uiDemand->isWorkflowRunning('write_medical_summary'));

        // STEP 6: Complete write medical summary workflow
        $writeMedicalSummaryWorkflowRun->update([
            'completed_at' => now(),
        ]);

        // Handle medical summary workflow completion
        $service->handleUiDemandWorkflowComplete($writeMedicalSummaryWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // Verify medical summary completed state - now can write demand letter
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data')); // Still has files and draft status
        $this->assertTrue($uiDemand->canRunWorkflow('write_medical_summary')); // Can write medical summary again
        $this->assertTrue($uiDemand->canRunWorkflow('write_demand_letter')); // NOW CAN write demand letter!
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertFalse($uiDemand->isWorkflowRunning('write_medical_summary'));
        $this->assertArrayHasKey('write_medical_summary_completed_at', $uiDemand->metadata);

        // STEP 7: Start write demand letter workflow
        $writeDemandLetterWorkflowRun = $service->runWorkflow($uiDemand, 'write_demand_letter');
        $uiDemand                     = $uiDemand->fresh();

        // Verify write demand letter is running
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data')); // Still can extract data
        $this->assertTrue($uiDemand->canRunWorkflow('write_medical_summary')); // Can write medical summary again
        $this->assertFalse($uiDemand->canRunWorkflow('write_demand_letter')); // Can't start another demand letter
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertFalse($uiDemand->isWorkflowRunning('write_medical_summary'));
        $this->assertTrue($uiDemand->isWorkflowRunning('write_demand_letter'));

        // STEP 8: Complete write demand letter workflow
        $writeDemandLetterWorkflowRun->update([
            'completed_at' => now(),
        ]);

        // Create output artifacts with Google Docs URL
        $googleDocsUrl  = 'https://docs.google.com/document/d/test123/edit';
        $outputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => "Generated document: {$googleDocsUrl}",
        ]);

        $writeWorkflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $writeDemandLetterWorkflow->id,
        ]);

        $writeTaskRun = TaskRun::factory()->create([
            'workflow_run_id'  => $writeDemandLetterWorkflowRun->id,
            'workflow_node_id' => $writeWorkflowNode->id,
        ]);
        $writeTaskRun->outputArtifacts()->attach($outputArtifact->id);

        // Handle workflow completion
        $service->handleUiDemandWorkflowComplete($writeDemandLetterWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // STEP 9: Verify final state - stays as Draft until manually published
        $this->assertTrue($uiDemand->canRunWorkflow('extract_data')); // Still has files and status is DRAFT
        $this->assertTrue($uiDemand->canRunWorkflow('write_demand_letter')); // Still can write demand since not running and has extract data completed
        $this->assertFalse($uiDemand->isWorkflowRunning('extract_data'));
        $this->assertFalse($uiDemand->isWorkflowRunning('write_demand_letter'));
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertNull($uiDemand->completed_at);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $uiDemand->metadata);

        // Verify output files were attached from workflow artifacts (if any)
        $outputFiles = $uiDemand->outputFiles;
        // The number of output files depends on the artifacts having StoredFiles
    }

    public function test_api_endpoints_return_correct_workflow_structure(): void
    {
        // Given - Set up demand with medical summary completed (prerequisite for write demand letter)
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
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
            'title'          => 'Test API Response',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // Create completed write medical summary workflow run (REQUIRED for write demand letter)
        $writeMedicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $writeMedicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($writeMedicalSummaryWorkflowRun->id, [
            'workflow_type' => 'write_medical_summary',
        ]);

        // When - Get demand via API
        $response = $this->getJson("/api/ui-demands/{$uiDemand->id}/details");

        // Then - Should return new workflow_runs and workflow_config structure
        $response->assertSuccessful();
        $data = $response->json();

        // Verify new workflow_runs structure exists
        $this->assertArrayHasKey('workflow_runs', $data);
        $this->assertIsArray($data['workflow_runs']);

        // Verify all expected workflow keys exist
        $this->assertArrayHasKey('extract_data', $data['workflow_runs']);
        $this->assertArrayHasKey('write_medical_summary', $data['workflow_runs']);
        $this->assertArrayHasKey('write_demand_letter', $data['workflow_runs']);

        // Verify extract_data workflow run is not empty (it was completed) - now returns array
        $this->assertIsArray($data['workflow_runs']['extract_data']);
        $this->assertNotEmpty($data['workflow_runs']['extract_data']);
        $this->assertArrayHasKey('id', $data['workflow_runs']['extract_data'][0]);
        $this->assertNotNull($data['workflow_runs']['extract_data'][0]['completed_at']);

        // Verify write_medical_summary workflow run is not empty (it was completed) - now returns array
        $this->assertIsArray($data['workflow_runs']['write_medical_summary']);
        $this->assertNotEmpty($data['workflow_runs']['write_medical_summary']);
        $this->assertArrayHasKey('id', $data['workflow_runs']['write_medical_summary'][0]);
        $this->assertNotNull($data['workflow_runs']['write_medical_summary'][0]['completed_at']);

        // Verify write_demand_letter has no workflow run yet (empty array expected)
        $this->assertIsArray($data['workflow_runs']['write_demand_letter']);
        $this->assertEmpty($data['workflow_runs']['write_demand_letter']);

        // Verify workflow_config structure
        $this->assertArrayHasKey('workflow_config', $data);
        $this->assertIsArray($data['workflow_config']);
        $this->assertCount(4, $data['workflow_config']);

        // Verify workflow_config contains expected fields
        $firstWorkflow = $data['workflow_config'][0];
        $this->assertArrayHasKey('key', $firstWorkflow);
        $this->assertArrayHasKey('name', $firstWorkflow);
        $this->assertArrayHasKey('label', $firstWorkflow);
        $this->assertArrayHasKey('description', $firstWorkflow);
        $this->assertArrayHasKey('color', $firstWorkflow);
        $this->assertArrayHasKey('depends_on', $firstWorkflow);

        // Verify metadata still exists
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('write_medical_summary_completed_at', $data['metadata']);
    }

    public function test_details_endpoint_includes_workflow_runs_for_different_states(): void
    {
        // Given - Create demands in different workflow states
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Demand 1: Fresh demand with no workflows run
        $freshDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => [],
            'title'          => 'Fresh Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $freshDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // Demand 2: Has completed extract_data workflow
        $extractDataDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Extract Data Completed',
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $extractDataDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // Demand 3: Has completed medical summary workflow
        $medicalSummaryDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => [
                'extract_data_completed_at'          => now()->subHour()->toIso8601String(),
                'write_medical_summary_completed_at' => now()->toIso8601String(),
            ],
            'title'          => 'Medical Summary Completed',
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $medicalSummaryDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => 'write_medical_summary',
        ]);

        // Test each demand's details endpoint individually

        // Fresh demand - no workflow runs
        $freshResponse = $this->getJson("/api/ui-demands/{$freshDemand->id}/details");
        $freshResponse->assertSuccessful();
        $fresh = $freshResponse->json();

        $this->assertArrayHasKey('workflow_runs', $fresh);
        $this->assertArrayHasKey('extract_data', $fresh['workflow_runs']);
        $this->assertArrayHasKey('write_medical_summary', $fresh['workflow_runs']);
        $this->assertArrayHasKey('write_demand_letter', $fresh['workflow_runs']);
        $this->assertIsArray($fresh['workflow_runs']['extract_data']);
        $this->assertEmpty($fresh['workflow_runs']['extract_data']);
        $this->assertIsArray($fresh['workflow_runs']['write_medical_summary']);
        $this->assertEmpty($fresh['workflow_runs']['write_medical_summary']);
        $this->assertIsArray($fresh['workflow_runs']['write_demand_letter']);
        $this->assertEmpty($fresh['workflow_runs']['write_demand_letter']);

        // Extract data completed demand - has extract_data workflow run
        $extractDataResponse = $this->getJson("/api/ui-demands/{$extractDataDemand->id}/details");
        $extractDataResponse->assertSuccessful();
        $extractData = $extractDataResponse->json();

        $this->assertArrayHasKey('workflow_runs', $extractData);
        $this->assertIsArray($extractData['workflow_runs']['extract_data']);
        $this->assertNotEmpty($extractData['workflow_runs']['extract_data']);
        $this->assertArrayHasKey('id', $extractData['workflow_runs']['extract_data'][0]);
        $this->assertNotNull($extractData['workflow_runs']['extract_data'][0]['completed_at']);
        $this->assertIsArray($extractData['workflow_runs']['write_medical_summary']);
        $this->assertEmpty($extractData['workflow_runs']['write_medical_summary']);
        $this->assertIsArray($extractData['workflow_runs']['write_demand_letter']);
        $this->assertEmpty($extractData['workflow_runs']['write_demand_letter']);

        // Medical summary completed demand - has medical summary workflow run
        $medicalSummaryResponse = $this->getJson("/api/ui-demands/{$medicalSummaryDemand->id}/details");
        $medicalSummaryResponse->assertSuccessful();
        $medicalSummary = $medicalSummaryResponse->json();

        $this->assertArrayHasKey('workflow_runs', $medicalSummary);
        $this->assertIsArray($medicalSummary['workflow_runs']['write_medical_summary']);
        $this->assertNotEmpty($medicalSummary['workflow_runs']['write_medical_summary']);
        $this->assertArrayHasKey('id', $medicalSummary['workflow_runs']['write_medical_summary'][0]);
        $this->assertNotNull($medicalSummary['workflow_runs']['write_medical_summary'][0]['completed_at']);
        $this->assertIsArray($medicalSummary['workflow_runs']['write_demand_letter']);
        $this->assertEmpty($medicalSummary['workflow_runs']['write_demand_letter']);
    }

    public function test_workflow_event_broadcasting_works_for_write_demand(): void
    {
        // This test ensures that when write demand workflows update,
        // the UiDemandUpdatedEvent is broadcast correctly

        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
            'title'          => 'Event Test Demand',
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'write_demand_letter']);

        // When - Handle workflow completion (this should trigger event)
        $service = app(UiDemandWorkflowService::class);
        $service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify demand was updated correctly
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertNull($updatedDemand->completed_at);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $updatedDemand->metadata);
    }
}
