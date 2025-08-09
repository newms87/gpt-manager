<?php

namespace Tests\Unit\Services\UiDemand;

use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use App\Services\Workflow\WorkflowRunnerService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;

class UiDemandWorkflowServiceTest extends AuthenticatedTestCase
{
    protected UiDemandWorkflowService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(UiDemandWorkflowService::class);
    }

    public function test_extract_data_creates_workflow_listener_and_runs_workflow()
    {
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'test-extract-workflow',
        ]);

        Config::set('app.demand_workflow_extract_data', 'test-extract-workflow');

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_READY,
            'title' => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->storedFiles()->attach($storedFile->id);

        // Use integration approach instead of mocking static methods

        $workflowRun = $this->service->extractData($uiDemand);

        // Assert workflow run was returned
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Assert workflow listener was created
        $this->assertDatabaseHas('workflow_listeners', [
            'listener_type' => UiDemand::class,
            'listener_id' => $uiDemand->id,
            'workflow_type' => WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA,
            'workflow_run_id' => $workflowRun->id,
            'status' => WorkflowListener::STATUS_RUNNING,
        ]);

        // Assert UI demand status was updated
        $this->assertEquals(UiDemand::STATUS_PROCESSING, $uiDemand->fresh()->status);
    }

    public function test_extract_data_throws_validation_error_when_cannot_extract()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT, // Wrong status
            'title' => 'Test Demand',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot extract data for this demand. Check status and existing workflows.');

        $this->service->extractData($uiDemand);
    }

    public function test_write_demand_creates_workflow_listener_and_runs_workflow()
    {
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'test-write-workflow',
        ]);

        Config::set('app.demand_workflow_write_demand', 'test-write-workflow');

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'team_object_id' => $teamObject->id,
            'title' => 'Test Demand',
        ]);

        // Create completed extract data workflow listener
        $extractWorkflowRun = WorkflowRun::factory()->create();
        $extractListener = WorkflowListener::createForListener(
            $uiDemand,
            $extractWorkflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );
        $extractListener->markAsCompleted();

        // Use integration approach instead of mocking static methods

        $workflowRun = $this->service->writeDemand($uiDemand);

        // Assert workflow run was returned
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Assert workflow listener was created
        $this->assertDatabaseHas('workflow_listeners', [
            'listener_type' => UiDemand::class,
            'listener_id' => $uiDemand->id,
            'workflow_type' => WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND,
            'workflow_run_id' => $workflowRun->id,
            'status' => WorkflowListener::STATUS_RUNNING,
        ]);

        // Assert UI demand status was updated
        $this->assertEquals(UiDemand::STATUS_PROCESSING, $uiDemand->fresh()->status);
    }

    public function test_write_demand_throws_validation_error_when_cannot_write()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'team_object_id' => null, // No team object
            'title' => 'Test Demand',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand. Check if extract data is completed and team object exists.');

        $this->service->writeDemand($uiDemand);
    }

    public function test_on_workflow_complete_handles_extract_data_success()
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'completed',
        ]);

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Mock artifact with team object
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta' => ['team_object_id' => $teamObject->id],
        ]);

        // Mock workflow run to return the artifact
        $workflowRun = $this->mock($workflowRun);
        $workflowRun->shouldReceive('isCompleted')->andReturn(true);
        $workflowRun->shouldReceive('collectFinalOutputArtifacts')->andReturn(collect([$artifact]));

        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Assert workflow listener was marked as completed
        $this->assertEquals(WorkflowListener::STATUS_COMPLETED, $workflowListener->fresh()->status);

        // Assert team object was associated with UI demand
        $this->assertEquals($teamObject->id, $uiDemand->fresh()->team_object_id);

        // Assert metadata was updated
        $updatedListener = $workflowListener->fresh();
        $this->assertArrayHasKey('team_object_id', $updatedListener->metadata);
        $this->assertEquals($teamObject->id, $updatedListener->metadata['team_object_id']);
    }

    public function test_on_workflow_complete_handles_write_demand_success()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'completed',
        ]);

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND
        );

        // Mock artifact with Google Docs URL
        $googleDocsUrl = 'https://docs.google.com/document/d/test123/edit';
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => "Generated document: {$googleDocsUrl}",
        ]);

        // Mock workflow run to return the artifact
        $workflowRun = $this->mock($workflowRun);
        $workflowRun->shouldReceive('isCompleted')->andReturn(true);
        $workflowRun->shouldReceive('collectFinalOutputArtifacts')->andReturn(collect([$artifact]));

        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Assert workflow listener was marked as completed
        $this->assertEquals(WorkflowListener::STATUS_COMPLETED, $workflowListener->fresh()->status);

        // Assert UI demand status was updated to completed
        $this->assertEquals(UiDemand::STATUS_COMPLETED, $uiDemand->fresh()->status);
        $this->assertNotNull($uiDemand->fresh()->completed_at);

        // Assert Google Docs stored file was created and associated
        $this->assertDatabaseHas('stored_files', [
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'url' => $googleDocsUrl,
            'disk' => 'external',
            'mime' => 'application/vnd.google-apps.document',
        ]);

        // Assert metadata was updated
        $updatedListener = $workflowListener->fresh();
        $this->assertArrayHasKey('google_docs_url', $updatedListener->metadata);
        $this->assertEquals($googleDocsUrl, $updatedListener->metadata['google_docs_url']);
    }

    public function test_on_workflow_complete_handles_failure()
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'title' => 'Test Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'failed',
        ]);

        $workflowListener = WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Mock workflow run as failed
        $workflowRun = $this->mock($workflowRun);
        $workflowRun->shouldReceive('isCompleted')->andReturn(false);
        $workflowRun->status = 'failed';

        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Assert workflow listener was marked as failed
        $this->assertEquals(WorkflowListener::STATUS_FAILED, $workflowListener->fresh()->status);

        // Assert UI demand status was updated to failed
        $this->assertEquals(UiDemand::STATUS_FAILED, $uiDemand->fresh()->status);

        // Assert metadata was updated with error
        $updatedListener = $workflowListener->fresh();
        $this->assertArrayHasKey('error', $updatedListener->metadata);
        $this->assertEquals('failed', $updatedListener->metadata['error']);
    }

}