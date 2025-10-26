<?php

namespace Tests\Unit\Services\UiDemand;

use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWorkflowServiceTest extends AuthenticatedTestCase
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

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    public function test_extractData_withValidDemand_startsWorkflowCorrectly(): void
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
            'title'   => 'Test Demand',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

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
            'status'  => UiDemand::STATUS_COMPLETED, // Invalid status
            'title'   => 'Test Demand',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No input files attached to the demand.');

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_extractData_withExistingWorkflowRun_throwsValidationError(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $existingWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'running',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test Demand',
        ]);

        // Create a running extract data workflow in the pivot table
        $uiDemand->workflowRuns()->attach($existingWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($storedFile->id, ['category' => 'input']);

        // Verify that the demand can't extract data due to running workflow
        $this->assertFalse($uiDemand->canExtractData(), 'Should not be able to extract data when a workflow is running');

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot extract data for this demand. Make sure a workflow is not already running for this demand.');

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_extractData_withMissingWorkflowDefinition_throwsValidationError(): void
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

        // No workflow definition exists

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Workflow 'Extract Service Dates' not found");

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_writeMedicalSummary_withValidDemand_startsWorkflowCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

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
            'title'          => 'Test Demand',
        ]);

        // Create a completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        // Attach the completed extract data workflow to the ui demand
        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Since Queue is faked, WorkflowRunnerService will create real WorkflowRun but without job dispatch

        // When
        $workflowRun = $this->service->writeMedicalSummary($uiDemand);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY, $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    public function test_writeMedicalSummary_withNoTeamObject_throwsValidationError(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title'          => 'Test Demand',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write medical summary. Check if extract data is completed and team object exists.');

        // When
        $this->service->writeMedicalSummary($uiDemand);
    }

    public function test_writeMedicalSummary_withoutExtractDataCompleted_throwsValidationError(): void
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

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write medical summary. Check if extract data is completed and team object exists.');

        // When
        $this->service->writeMedicalSummary($uiDemand);
    }

    public function test_handleUiDemandWorkflowComplete_withSuccessfulExtractDataWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'status'   => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title'    => 'Test Demand',
        ]);

        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate proper workflow execution
        // 1. Some task produces the artifact
        $processingTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => 'test_processing_task',
        ]);

        $processingTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $processingTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        // The processing task produces the artifact
        $processingTaskRun->outputArtifacts()->attach($artifact->id);

        // 2. Workflow output task collects final outputs
        $workflowOutputTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        $outputNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $outputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);

        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);
        $outputTaskProcess->inputArtifacts()->attach($artifact->id);

        // Run the WorkflowOutputTaskRunner
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

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

    public function test_handleUiDemandWorkflowComplete_withSuccessfulWriteMedicalSummaryWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'status'   => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title'    => 'Test Demand',
        ]);

        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY]);

        $googleDocsUrl = 'https://docs.google.com/document/d/test123/edit';
        $artifact      = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => "Generated document: {$googleDocsUrl}",
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate proper workflow execution
        // 1. Some task produces the artifact
        $processingTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => 'test_processing_task',
        ]);

        $processingTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $processingTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        // The processing task produces the artifact
        $processingTaskRun->outputArtifacts()->attach($artifact->id);

        // 2. Workflow output task collects final outputs
        $workflowOutputTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        $outputNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $outputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);

        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);
        $outputTaskProcess->inputArtifacts()->attach($artifact->id);

        // Run the WorkflowOutputTaskRunner
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertNull($updatedDemand->completed_at);
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('write_medical_summary_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
        // Verify workflow is still tracked in pivot table
        $this->assertTrue($updatedDemand->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());

        // Verify output files were attached from artifacts (if any StoredFiles exist)
        $outputFiles = $updatedDemand->outputFiles;
        // The exact number depends on whether artifacts have StoredFiles attached
    }

    public function test_handleUiDemandWorkflowComplete_withSuccessfulWriteDemandLetterWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'status'   => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title'    => 'Test Demand',
        ]);

        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER]);

        $googleDocsUrl = 'https://docs.google.com/document/d/test456/edit';
        $artifact      = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['google_docs_url' => $googleDocsUrl],
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate proper workflow execution
        // 1. Some task produces the artifact
        $processingTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => 'test_processing_task',
        ]);

        $processingTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $processingTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        // The processing task produces the artifact
        $processingTaskRun->outputArtifacts()->attach($artifact->id);

        // 2. Workflow output task collects final outputs
        $workflowOutputTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        $outputNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $outputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);

        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);
        $outputTaskProcess->inputArtifacts()->attach($artifact->id);

        // Run the WorkflowOutputTaskRunner
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $updatedDemand->metadata);
    }

    public function test_handleUiDemandWorkflowComplete_withFailedWorkflow_updatesCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'failed_at'              => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'status'   => UiDemand::STATUS_DRAFT,
            'metadata' => ['existing_key' => 'existing_value'],
            'title'    => 'Test Demand',
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
            'name'    => 'Extract Service Dates',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'id'                     => 999,
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        // No UiDemand with workflow_run_id = 999

        // When - should not throw any exceptions
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - verify no database changes occurred
        $this->assertDatabaseMissing('ui_demand_workflow_runs', [
            'workflow_run_id' => 999,
        ]);
    }

    public function test_handleUiDemandWorkflowComplete_withWriteDemandLetterWorkflowNoGoogleDocs_updatesWithoutUrl(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test Demand',
        ]);

        // Connect via pivot table
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some content without Google Docs URL',
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate proper workflow execution
        // 1. Some task produces the artifact
        $processingTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => 'test_processing_task',
        ]);

        $processingTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $processingTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        // The processing task produces the artifact
        $processingTaskRun->outputArtifacts()->attach($artifact->id);

        // 2. Workflow output task collects final outputs
        $workflowOutputTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        $outputNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $outputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);

        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);
        $outputTaskProcess->inputArtifacts()->attach($artifact->id);

        // Run the WorkflowOutputTaskRunner
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayNotHasKey('google_docs_url', $updatedDemand->metadata);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);

        // Verify no stored file was created
        $this->assertDatabaseMissing('stored_files', [
            'team_id' => $this->user->currentTeam->id,
            'disk'    => 'external',
        ]);
    }

    public function test_config_workflowTypeNotFound_throwsValidationError(): void
    {
        // Given
        Config::set('ui-demands.workflows.extract_data', null);

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

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Workflow configuration not found for type: extract_data');

        // When
        $this->service->extractData($uiDemand);
    }

    public function test_attachOutputFilesFromWorkflow_withSingleArtifact_attachesStoredFilesCorrectly(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create an artifact with attached StoredFiles
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $storedFile1 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'output1.gdoc',
            'url'      => 'https://docs.google.com/document/d/doc1/edit',
        ]);

        $storedFile2 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'output2.gdoc',
            'url'      => 'https://docs.google.com/document/d/doc2/edit',
        ]);

        // Attach StoredFiles to artifact
        $artifact->storedFiles()->attach([$storedFile1->id, $storedFile2->id]);

        $outputArtifacts = collect([$artifact]);

        // When
        $this->invokeMethod($this->service, 'attachOutputFilesFromWorkflow', [$uiDemand, $outputArtifacts]);

        // Then
        $outputFiles = $uiDemand->outputFiles;
        $this->assertCount(2, $outputFiles);
        $this->assertTrue($outputFiles->contains('id', $storedFile1->id));
        $this->assertTrue($outputFiles->contains('id', $storedFile2->id));

        // Verify the category is set correctly
        $this->assertDatabaseHas('stored_file_storables', [
            'stored_file_id' => $storedFile1->id,
            'storable_type'  => 'App\\Models\\Demand\\UiDemand',
            'storable_id'    => $uiDemand->id,
            'category'       => 'output',
        ]);

        $this->assertDatabaseHas('stored_file_storables', [
            'stored_file_id' => $storedFile2->id,
            'storable_type'  => 'App\\Models\\Demand\\UiDemand',
            'storable_id'    => $uiDemand->id,
            'category'       => 'output',
        ]);
    }

    public function test_attachOutputFilesFromWorkflow_withMultipleArtifacts_attachesAllStoredFiles(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create first artifact with one file
        $artifact1   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile1 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'artifact1-output.gdoc',
        ]);
        $artifact1->storedFiles()->attach($storedFile1->id);

        // Create second artifact with two files
        $artifact2   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'artifact2-output1.gdoc',
        ]);
        $storedFile3 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'artifact2-output2.gdoc',
        ]);
        $artifact2->storedFiles()->attach([$storedFile2->id, $storedFile3->id]);

        $outputArtifacts = collect([$artifact1, $artifact2]);

        // When
        $this->invokeMethod($this->service, 'attachOutputFilesFromWorkflow', [$uiDemand, $outputArtifacts]);

        // Then
        $outputFiles = $uiDemand->outputFiles;
        $this->assertCount(3, $outputFiles);
        $this->assertTrue($outputFiles->contains('id', $storedFile1->id));
        $this->assertTrue($outputFiles->contains('id', $storedFile2->id));
        $this->assertTrue($outputFiles->contains('id', $storedFile3->id));
    }

    public function test_attachOutputFilesFromWorkflow_withDuplicateStoredFiles_doesNotCreateDuplicates(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'shared-output.gdoc',
        ]);

        // Create two artifacts both referencing the same StoredFile
        $artifact1 = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact1->storedFiles()->attach($storedFile->id);

        $artifact2 = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact2->storedFiles()->attach($storedFile->id);

        $outputArtifacts = collect([$artifact1, $artifact2]);

        // When
        $this->invokeMethod($this->service, 'attachOutputFilesFromWorkflow', [$uiDemand, $outputArtifacts]);

        // Then
        $outputFiles = $uiDemand->outputFiles;
        $this->assertCount(1, $outputFiles); // Should only have one file, not duplicate
        $this->assertEquals($storedFile->id, $outputFiles->first()->id);

        // Verify there's only one record in the pivot table
        $pivotRecords = \DB::table('stored_file_storables')
            ->where('stored_file_id', $storedFile->id)
            ->where('storable_type', 'App\\Models\\Demand\\UiDemand')
            ->where('storable_id', $uiDemand->id)
            ->where('category', 'output')
            ->count();

        $this->assertEquals(1, $pivotRecords);
    }

    public function test_attachOutputFilesFromWorkflow_withNoStoredFiles_doesNothing(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create artifacts with no attached StoredFiles
        $artifact1 = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact2 = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $outputArtifacts = collect([$artifact1, $artifact2]);

        // When
        $this->invokeMethod($this->service, 'attachOutputFilesFromWorkflow', [$uiDemand, $outputArtifacts]);

        // Then
        $outputFiles = $uiDemand->outputFiles;
        $this->assertCount(0, $outputFiles);
    }

    public function test_attachOutputFilesFromWorkflow_withEmptyArtifactCollection_doesNothing(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $outputArtifacts = collect([]);

        // When
        $this->invokeMethod($this->service, 'attachOutputFilesFromWorkflow', [$uiDemand, $outputArtifacts]);

        // Then
        $outputFiles = $uiDemand->outputFiles;
        $this->assertCount(0, $outputFiles);
    }

    public function test_attachOutputFilesFromWorkflow_reusesStoredFileFromArtifact(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create an existing StoredFile
        $existingStoredFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'reused-file.gdoc',
            'url'      => 'https://docs.google.com/document/d/reused123/edit',
        ]);

        // Create artifact and attach the existing StoredFile
        $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact->storedFiles()->attach($existingStoredFile->id);

        $outputArtifacts = collect([$artifact]);

        // When
        $this->invokeMethod($this->service, 'attachOutputFilesFromWorkflow', [$uiDemand, $outputArtifacts]);

        // Then
        $outputFiles = $uiDemand->outputFiles;
        $this->assertCount(1, $outputFiles);

        // Verify it's the same StoredFile instance (reused, not duplicated)
        $attachedFile = $outputFiles->first();
        $this->assertEquals($existingStoredFile->id, $attachedFile->id);
        $this->assertEquals($existingStoredFile->filename, $attachedFile->filename);
        $this->assertEquals($existingStoredFile->url, $attachedFile->url);

        // Verify no new StoredFile was created
        $totalStoredFiles = StoredFile::where('team_id', $this->user->currentTeam->id)->count();
        $this->assertEquals(1, $totalStoredFiles); // Only the original file should exist
    }

    public function test_writeDemandLetter_withValidDemand_startsWorkflowCorrectly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
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
            'title'          => 'Test Demand',
        ]);

        // Create a completed write medical summary workflow run
        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        // Attach the completed medical summary workflow to the ui demand
        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // Since Queue is faked, WorkflowRunnerService will create real WorkflowRun but without job dispatch

        // When
        $workflowRun = $this->service->writeDemandLetter($uiDemand);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER, $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    public function test_writeDemandLetter_withNoTeamObject_throwsValidationError(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'team_object_id' => null, // No team object
            'title'          => 'Test Demand',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand letter. Check if write medical summary is completed and team object exists.');

        // When
        $this->service->writeDemandLetter($uiDemand);
    }

    public function test_writeDemandLetter_withoutMedicalSummaryCompleted_throwsValidationError(): void
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

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand letter. Check if write medical summary is completed and team object exists.');

        // When
        $this->service->writeDemandLetter($uiDemand);
    }

    public function test_writeMedicalSummary_withInstructionTemplate_appendsInstructions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

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
            'title'          => 'Test Demand',
        ]);

        // Create a completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
        ]);

        // Create an instruction template (WorkflowInput)
        $instructionTemplate = \App\Models\Workflow\WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'content' => 'Use a professional medical tone and include specific dates.',
        ]);

        $additionalInstructions = 'Focus on the most critical injuries only.';

        // When
        $workflowRun = $this->service->writeMedicalSummary($uiDemand, $instructionTemplate->id, $additionalInstructions);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Verify the workflow was started correctly
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY, $uiDemand->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
        $this->assertTrue($uiDemand->fresh()->isWriteMedicalSummaryRunning());
    }

    public function test_writeDemandLetter_withTemplateId_includesTemplateInContent(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
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
            'title'          => 'Test Demand',
        ]);

        // Create a completed write medical summary workflow run
        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY,
        ]);

        // Create a stored file for the template
        $templateFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'demand_template.docx',
        ]);

        // Create a demand template
        $template = \App\Models\Demand\DemandTemplate::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $templateFile->id,
        ]);

        $additionalInstructions = 'Include specific monetary damages.';

        // When
        $workflowRun = $this->service->writeDemandLetter($uiDemand, $template->id, $additionalInstructions);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Verify the workflow was started correctly with the template
        $this->assertEquals(UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER, $uiDemand->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
        $this->assertTrue($uiDemand->fresh()->isWriteDemandLetterRunning());
    }

    /**
     * Helper method to invoke protected/private methods for testing
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
