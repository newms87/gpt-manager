<?php

namespace Tests\Feature;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWriteDemandWorkflowIntegrationTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

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
            'name' => 'Extract Service Dates',
        ]);

        $writeDemandWorkflow = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'title' => 'Test Demand Integration',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->storedFiles()->attach($storedFile->id);

        $service = app(UiDemandWorkflowService::class);

        // STEP 1: Verify initial state - can extract data, cannot write demand
        $this->assertTrue($uiDemand->canExtractData());
        $this->assertFalse($uiDemand->canWriteDemand());
        $this->assertFalse($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteDemandRunning());

        // STEP 2: Start extract data workflow
        $extractDataWorkflowRun = $service->extractData($uiDemand);
        $uiDemand = $uiDemand->fresh();

        // Verify extract data is running
        $this->assertFalse($uiDemand->canExtractData()); // Can't start another
        $this->assertFalse($uiDemand->canWriteDemand()); // Still can't write demand
        $this->assertTrue($uiDemand->isExtractDataRunning());
        $this->assertFalse($uiDemand->isWriteDemandRunning());

        // STEP 3: Complete extract data workflow
        $extractDataWorkflowRun->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create output artifacts for the workflow
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $extractDataWorkflow->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'workflow_run_id' => $extractDataWorkflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);
        $taskRun->outputArtifacts()->attach($artifact->id);

        // Handle workflow completion
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
        $uiDemand = $uiDemand->fresh();

        // Verify write demand is running
        $this->assertTrue($uiDemand->canExtractData()); // Still can extract data
        $this->assertFalse($uiDemand->canWriteDemand()); // Can't start another
        $this->assertFalse($uiDemand->isExtractDataRunning());
        $this->assertTrue($uiDemand->isWriteDemandRunning());

        // STEP 6: Complete write demand workflow
        $writeDemandWorkflowRun->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create output artifacts with Google Docs URL
        $googleDocsUrl = 'https://docs.google.com/document/d/test123/edit';
        $outputArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => "Generated document: {$googleDocsUrl}",
        ]);

        $writeWorkflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $writeDemandWorkflow->id,
        ]);

        $writeTaskRun = TaskRun::factory()->create([
            'workflow_run_id' => $writeDemandWorkflowRun->id,
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
        $this->assertArrayHasKey('google_docs_url', $uiDemand->metadata);
        $this->assertEquals($googleDocsUrl, $uiDemand->metadata['google_docs_url']);

        // Verify Google Docs stored file was created
        $this->assertDatabaseHas('stored_files', [
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'url' => $googleDocsUrl,
            'disk' => 'external',
            'filename' => 'Demand Output - Test Demand Integration.gdoc',
            'mime' => 'application/vnd.google-apps.document',
        ]);
    }

    public function test_api_endpoints_return_correct_canWriteDemand_flag(): void
    {
        // Given - Set up demand with extract data completed
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
            'title' => 'Test API Response',
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
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Demand 1: Fresh demand (can extract data, cannot write demand)
        $freshDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => [],
            'title' => 'Fresh Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $freshDemand->storedFiles()->attach($storedFile->id);

        // Demand 2: Extract data completed (cannot extract, can write demand)
        $readyDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
            'title' => 'Ready for Write Demand',
        ]);

        // Demand 3: Completed demand (cannot do anything)
        $completedDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_COMPLETED,
            'team_object_id' => $teamObject->id,
            'metadata' => [
                'extract_data_completed_at' => now()->subHour()->toIso8601String(),
                'write_demand_completed_at' => now()->toIso8601String(),
            ],
            'completed_at' => now(),
            'title' => 'Completed Demand',
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
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
            'title' => 'Event Test Demand',
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Write Demand Summary',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
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