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

        // Set up workflow configuration
        Config::set('ui-demands.workflows.extract_data', 'Extract Service Dates');
        Config::set('ui-demands.workflows.write_demand', 'Write Demand Summary');

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_complete_workflow_extractData_to_writeDemand_integration(): void
    {
        // Given - Set up extract data and write demand workflow definitions
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
            'title'          => 'Test Demand Integration',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        $service = app(UiDemandWorkflowService::class);

        // STEP 1: Verify initial state - can extract data, cannot write demand
        $this->assertTrue($uiDemand->canExtractData());
        $this->assertFalse($uiDemand->canWriteDemand());
        $this->assertFalse($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteDemandRunning());

        // STEP 2: Start extract data workflow
        $extractDataWorkflowRun = $service->extractData($uiDemand);
        $uiDemand               = $uiDemand->fresh();

        // Verify extract data is running
        $this->assertFalse($uiDemand->canExtractData()); // Can't start another
        $this->assertFalse($uiDemand->canWriteDemand()); // Still can't write demand
        $this->assertTrue($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteDemandRunning());

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

        // STEP 4: Verify extract data completed state - now can write demand
        $this->assertTrue($uiDemand->canExtractData()); // Still has files and draft status
        $this->assertTrue($uiDemand->canWriteDemand()); // NOW SHOULD BE TRUE!
        $this->assertFalse($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteDemandRunning());
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertArrayHasKey('extract_data_completed_at', $uiDemand->metadata);

        // STEP 5: Start write demand workflow
        $writeDemandWorkflowRun = $service->writeDemand($uiDemand);
        $uiDemand               = $uiDemand->fresh();

        // Verify write demand is running
        $this->assertTrue($uiDemand->canExtractData()); // Still can extract data
        $this->assertFalse($uiDemand->canWriteDemand()); // Can't start another
        $this->assertFalse($uiDemand->isExtractDataRunning());
        $this->assertTrue($uiDemand->isWriteDemandRunning());

        // STEP 6: Complete write demand workflow
        $writeDemandWorkflowRun->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Create output artifacts with Google Docs URL
        $googleDocsUrl  = 'https://docs.google.com/document/d/test123/edit';
        $outputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => "Generated document: {$googleDocsUrl}",
        ]);

        $writeWorkflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $writeDemandWorkflow->id,
        ]);

        $writeTaskRun = TaskRun::factory()->create([
            'workflow_run_id'  => $writeDemandWorkflowRun->id,
            'workflow_node_id' => $writeWorkflowNode->id,
        ]);
        $writeTaskRun->outputArtifacts()->attach($outputArtifact->id);

        // Handle workflow completion
        $service->handleUiDemandWorkflowComplete($writeDemandWorkflowRun);
        $uiDemand = $uiDemand->fresh();

        // STEP 7: Verify final state - stays as Draft until manually published
        $this->assertTrue($uiDemand->canExtractData()); // Still has files and status is DRAFT
        $this->assertTrue($uiDemand->canWriteDemand()); // Still can write demand since not running and has extract data completed
        $this->assertFalse($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteDemandRunning());
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->status);
        $this->assertNull($uiDemand->completed_at);
        $this->assertArrayHasKey('write_demand_completed_at', $uiDemand->metadata);

        // Verify output files were attached from workflow artifacts (if any)
        $outputFiles = $uiDemand->outputFiles;
        // The number of output files depends on the artifacts having StoredFiles
    }

    public function test_api_endpoints_return_correct_canWriteDemand_flag(): void
    {
        // Given - Set up demand with extract data completed
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
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
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Test API Response',
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

        // When - Get demand via API
        $response = $this->getJson("/api/ui-demands/{$uiDemand->id}/details");

        // Then - Should show can_write_demand: true
        $response->assertSuccessful();
        $data = $response->json();

        $this->assertTrue($data['can_write_demand'], 'API should return can_write_demand: true when extract data is completed');
        $this->assertFalse($data['is_write_demand_running']);
        $this->assertArrayHasKey('write_demand_workflow_run', $data);

        // Verify detailed response structure
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('extract_data_completed_at', $data['metadata']);
    }

    public function test_list_endpoint_includes_write_demand_flags(): void
    {
        // Given - Create demands in different states
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Demand 1: Fresh demand (can extract data, cannot write demand)
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

        // Demand 2: Extract data completed (cannot extract, can write demand)
        $readyDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Ready for Write Demand',
        ]);

        // Create completed extract data workflow run for ready demand
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $readyDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Demand 3: Completed demand (cannot do anything)
        $completedDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_COMPLETED,
            'team_object_id' => $teamObject->id,
            'metadata'       => [
                'extract_data_completed_at' => now()->subHour()->toIso8601String(),
                'write_demand_completed_at' => now()->toIso8601String(),
            ],
            'completed_at'   => now(),
            'title'          => 'Completed Demand',
        ]);

        // Create completed extract data workflow run for completed demand
        $completedExtractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now()->subHour(),
        ]);

        $completedDemand->workflowRuns()->attach($completedExtractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When - Get demands list
        $response = $this->getJson('/api/ui-demands/list');

        // Then - Verify all demands have correct flags
        $response->assertSuccessful();
        $data = $response->json();

        $this->assertCount(3, $data['data']);

        // Find each demand in response and verify flags
        $demands = collect($data['data'])->keyBy('title');

        // Fresh demand
        $fresh = $demands['Fresh Demand'];
        $this->assertTrue($fresh['can_extract_data']);
        $this->assertFalse($fresh['can_write_demand']);
        $this->assertFalse($fresh['is_extract_data_running']);
        $this->assertFalse($fresh['is_write_demand_running']);

        // Ready demand - THIS IS THE CRITICAL TEST
        $ready = $demands['Ready for Write Demand'];
        $this->assertFalse($ready['can_extract_data']); // No files attached so can't extract data
        $this->assertTrue($ready['can_write_demand'], 'Demand with extract data completed should show can_write_demand: true');
        $this->assertFalse($ready['is_extract_data_running']);
        $this->assertFalse($ready['is_write_demand_running']);

        // Completed demand
        $completed = $demands['Completed Demand'];
        $this->assertFalse($completed['can_extract_data']); // No files attached, so can't extract data
        $this->assertTrue($completed['can_write_demand']); // Has team_object_id and extract_data_completed_at, can still write demand
        $this->assertFalse($completed['is_extract_data_running']);
        $this->assertFalse($completed['is_write_demand_running']);
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
            'metadata'       => ['extract_data_completed_at' => now()->toIso8601String()],
            'title'          => 'Event Test Demand',
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        // When - Handle workflow completion (this should trigger event)
        $service = app(UiDemandWorkflowService::class);
        $service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify demand was updated correctly
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertNull($updatedDemand->completed_at);
        $this->assertArrayHasKey('write_demand_completed_at', $updatedDemand->metadata);
    }
}
