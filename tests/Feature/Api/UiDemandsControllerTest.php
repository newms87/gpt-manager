<?php

namespace Tests\Feature\Api;

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

class UiDemandsControllerTest extends AuthenticatedTestCase
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

    public function test_extractData_withValidRequest_returnsSuccessResponse(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'title' => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->storedFiles()->attach($storedFile->id);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/extract-data");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'can_extract_data',
            'can_write_demand',
            'is_extract_data_running',
            'extract_data_workflow_run' => [
                'id',
                'status',
                'progress_percent'
            ]
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
            'status' => UiDemand::STATUS_COMPLETED, // Invalid status
            'title' => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/extract-data");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error'
        ]);
    }

    public function test_writeDemand_withValidRequest_returnsSuccessResponse(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
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
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
            'title' => 'Test Demand',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand");

        // Then
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'title',
            'status',
            'can_write_demand',
            'is_write_demand_running',
            'write_demand_workflow_run' => [
                'id',
                'status',
                'progress_percent'
            ]
        ]);

        // Verify workflow was started
        $workflowRuns = $uiDemand->fresh()->workflowRuns;
        $this->assertTrue($workflowRuns->count() > 0);
        $this->assertTrue($uiDemand->fresh()->isWriteDemandRunning());
    }

    public function test_writeDemand_withInvalidDemand_returns400Error(): void
    {
        // Given - demand without team object
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title' => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error'
        ]);
        $response->assertJsonFragment([
            'message' => 'Failed to start write demand workflow.'
        ]);
    }

    public function test_writeDemand_withoutExtractDataCompleted_returns400Error(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => [], // No extract data completed metadata
            'title' => 'Test Demand',
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand");

        // Then
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error'
        ]);
    }

    public function test_extractData_endpoint_loadsCorrectRelationships(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'title' => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->storedFiles()->attach($storedFile->id);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/extract-data");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('files', $data);
        $this->assertArrayHasKey('extract_data_workflow_run', $data);
        
        // Verify workflow run has necessary data
        $this->assertNotNull($data['extract_data_workflow_run']);
        $this->assertArrayHasKey('progress_percent', $data['extract_data_workflow_run']);
        $this->assertArrayHasKey('total_nodes', $data['extract_data_workflow_run']);
        $this->assertArrayHasKey('completed_tasks', $data['extract_data_workflow_run']);
    }

    public function test_writeDemand_endpoint_loadsCorrectRelationships(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
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
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()],
            'title' => 'Test Demand',
        ]);

        // Create completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // When
        $response = $this->postJson("/api/ui-demands/{$uiDemand->id}/write-demand");

        // Then
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('team_object', $data);
        $this->assertArrayHasKey('write_demand_workflow_run', $data);
        
        // Verify workflow run has necessary data
        $this->assertNotNull($data['write_demand_workflow_run']);
        $this->assertArrayHasKey('progress_percent', $data['write_demand_workflow_run']);
        $this->assertArrayHasKey('total_nodes', $data['write_demand_workflow_run']);
        $this->assertArrayHasKey('completed_tasks', $data['write_demand_workflow_run']);
    }

    public function test_demand_canWriteDemand_flagIsCorrect(): void
    {
        // Given - fresh demand without extract data completed
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => [],
            'title' => 'Test Demand',
        ]);

        // When - get demand details
        $response = $this->getJson("/api/ui-demands/{$uiDemand->id}/details");

        // Then - can_write_demand should be false
        $response->assertSuccessful();
        $data = $response->json();
        $this->assertFalse($data['can_write_demand']);

        // Now complete extract data and verify can_write_demand becomes true
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        $uiDemand->update([
            'metadata' => ['extract_data_completed_at' => now()->toIso8601String()]
        ]);

        $response = $this->getJson("/api/ui-demands/{$uiDemand->id}/details");
        $response->assertSuccessful();
        $data = $response->json();
        $this->assertTrue($data['can_write_demand']);
    }

    public function test_workflow_completion_updates_canWriteDemand_correctly(): void
    {
        // Given - Set up extract data workflow that will complete
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => $teamObject->id,
            'metadata' => [],
            'title' => 'Test Demand',
        ]);
        
        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        // Should be able to write demand since extract data workflow is completed
        $this->assertTrue($uiDemand->canWriteDemand());

        // When - Handle workflow completion
        $service = app(UiDemandWorkflowService::class);
        $service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Should now be able to write demand
        $updatedDemand = $uiDemand->fresh();
        $this->assertTrue($updatedDemand->canWriteDemand());
        $this->assertArrayHasKey('extract_data_completed_at', $updatedDemand->metadata);
    }
}