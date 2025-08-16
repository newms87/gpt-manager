<?php

namespace Tests\Unit\Services\UiDemand;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowInputRepository;
use App\Services\UiDemand\UiDemandWorkflowService;
use App\Services\Workflow\WorkflowRunnerService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWorkflowServiceTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected UiDemandWorkflowService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(UiDemandWorkflowService::class);

        // Set up workflow configuration
        Config::set('ui-demands.workflows.extract_data', 'Extract Service Dates');
        Config::set('ui-demands.workflows.write_demand', 'Write Demand Summary');
        
        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_extractData_withValidDemand_startsWorkflowCorrectly(): void
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

        // Since Queue is faked, WorkflowRunnerService will create real WorkflowRun but without job dispatch

        // When
        $workflowRun = $this->service->extractData($uiDemand);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_EXTRACT_DATA, $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    public function test_extractData_withInvalidStatus_throwsValidationError(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_COMPLETED, // Invalid status
            'title' => 'Test Demand',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot extract data for this demand. Check status and existing workflows.');

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_extractData_withExistingWorkflowRun_throwsValidationError(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);
        
        $existingWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'running'
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'title' => 'Test Demand',
        ]);
        
        // Create a running extract data workflow in the pivot table
        $uiDemand->workflowRuns()->attach($existingWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->storedFiles()->attach($storedFile->id);

        // Verify that the demand can't extract data due to running workflow
        $this->assertFalse($uiDemand->canExtractData(), 'Should not be able to extract data when a workflow is running');

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot extract data for this demand. Check status and existing workflows.');

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_extractData_withMissingWorkflowDefinition_throwsValidationError(): void
    {
        // Given
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

        // No workflow definition exists

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Workflow 'Extract Service Dates' not found");

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_writeDemand_withValidDemand_startsWorkflowCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Write Demand Summary',
        ]);

        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Data',
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

        // Create a completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at' => now(),
        ]);

        // Attach the completed extract data workflow to the ui demand
        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Since Queue is faked, WorkflowRunnerService will create real WorkflowRun but without job dispatch

        // When
        $workflowRun = $this->service->writeDemand($uiDemand);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_WRITE_DEMAND, $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    public function test_writeDemand_withNoTeamObject_throwsValidationError(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title' => 'Test Demand',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand. Check if extract data is completed and team object exists.');

        // When
        $this->service->writeDemand($uiDemand);
    }

    public function test_writeDemand_withoutExtractDataCompleted_throwsValidationError(): void
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

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand. Check if extract data is completed and team object exists.');

        // When
        $this->service->writeDemand($uiDemand);
    }

    public function test_handleUiDemandWorkflowComplete_withSuccessfulExtractDataWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title' => 'Test Demand',
        ]);
        
        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);
        $taskRun->outputArtifacts()->attach($artifact->id);

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('extract_data_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
        // Verify workflow is still tracked in pivot table
        $this->assertTrue($updatedDemand->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
    }

    public function test_handleUiDemandWorkflowComplete_withSuccessfulWriteDemandWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Write Demand Summary',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title' => 'Test Demand',
        ]);
        
        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        $googleDocsUrl = 'https://docs.google.com/document/d/test123/edit';
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => "Generated document: {$googleDocsUrl}",
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);
        $taskRun->outputArtifacts()->attach($artifact->id);

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertNull($updatedDemand->completed_at);
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('write_demand_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertArrayHasKey('google_docs_url', $updatedDemand->metadata);
        $this->assertEquals($googleDocsUrl, $updatedDemand->metadata['google_docs_url']);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
        // Verify workflow is still tracked in pivot table
        $this->assertTrue($updatedDemand->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());

        // Verify Google Docs stored file was created
        $this->assertDatabaseHas('stored_files', [
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'url' => $googleDocsUrl,
            'disk' => 'external',
            'filename' => 'Demand Output - Test Demand.gdoc',
            'mime' => 'application/vnd.google-apps.document',
        ]);
    }

    public function test_handleUiDemandWorkflowComplete_withSuccessfulWriteDemandWorkflowFromJson_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Write Demand Summary',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title' => 'Test Demand',
        ]);
        
        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        $googleDocsUrl = 'https://docs.google.com/document/d/test456/edit';
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'json_content' => ['google_docs_url' => $googleDocsUrl],
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);
        $taskRun->outputArtifacts()->attach($artifact->id);

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('google_docs_url', $updatedDemand->metadata);
        $this->assertEquals($googleDocsUrl, $updatedDemand->metadata['google_docs_url']);
    }

    public function test_handleUiDemandWorkflowComplete_withFailedWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'failed',
            'failed_at' => now(),
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title' => 'Test Demand',
        ]);
        
        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_FAILED, $updatedDemand->status);
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('failed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('error', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals('Failed', $updatedDemand->metadata['error']);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
        // Verify workflow is still tracked in pivot table
        $this->assertTrue($updatedDemand->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
    }

    public function test_handleUiDemandWorkflowComplete_withNoMatchingDemand_doesNothing(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'id' => 999,
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // No UiDemand with workflow_run_id = 999

        // When - should not throw any exceptions
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - verify no database changes occurred
        $this->assertDatabaseMissing('ui_demand_workflow_runs', [
            'workflow_run_id' => 999,
        ]);
    }

    public function test_handleUiDemandWorkflowComplete_withWriteDemandWorkflowNoGoogleDocs_updatesWithoutUrl(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Write Demand Summary',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
            'title' => 'Test Demand',
        ]);
        
        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => 'Some content without Google Docs URL',
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);
        $taskRun->outputArtifacts()->attach($artifact->id);

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayNotHasKey('google_docs_url', $updatedDemand->metadata);
        $this->assertArrayHasKey('write_demand_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);

        // Verify no stored file was created
        $this->assertDatabaseMissing('stored_files', [
            'team_id' => $this->user->currentTeam->id,
            'disk' => 'external',
        ]);
    }

    public function test_config_workflowTypeNotFound_throwsValidationError(): void
    {
        // Given
        Config::set('ui-demands.workflows.extract_data', null);

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

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow configuration not found for type: extract_data');

        // When
        $this->service->extractData($uiDemand);
    }
}