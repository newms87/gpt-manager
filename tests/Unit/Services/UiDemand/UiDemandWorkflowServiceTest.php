<?php

namespace Tests\Unit\Services\UiDemand;

use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
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

        // Mock queue to prevent actual job dispatching
        Queue::fake();
    }

    #[Test]
    public function runWorkflow_withExtractDataWorkflow_startsCorrectly(): void
    {
        // Given
        $organizeFilesWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Organize Files',
        ]);

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

        // Complete organize_files workflow first (dependency for extract_data)
        $organizeFilesWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $organizeFilesWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($organizeFilesWorkflowRun->id, [
            'workflow_type' => 'organize_files',
        ]);

        // Create artifacts with organized_file category for extract_data workflow to use
        $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $organizeFilesWorkflowRun->artifacts()->attach($artifact->id, ['category' => 'organized_file']);

        // When
        $workflowRun = $this->service->runWorkflow($uiDemand, 'extract_data', []);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals('extract_data', $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    #[Test]
    public function runWorkflow_withWriteMedicalSummaryWorkflow_startsCorrectly(): void
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
            'title'          => 'Test Demand',
        ]);

        // Create a completed extract data workflow run
        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        // When
        $workflowRun = $this->service->runWorkflow($uiDemand, 'write_medical_summary', []);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals('write_medical_summary', $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    #[Test]
    public function runWorkflow_withWriteDemandLetterWorkflow_startsCorrectly(): void
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
            'title'          => 'Test Demand',
        ]);

        // Create a completed write medical summary workflow run
        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => 'write_medical_summary',
        ]);

        // Create medical summary artifacts attached to TeamObject
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Medical summary content',
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'medical_summary']);

        // When
        $workflowRun = $this->service->runWorkflow($uiDemand, 'write_demand_letter', []);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals(UiDemand::STATUS_DRAFT, $uiDemand->fresh()->status);
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals('write_demand_letter', $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    #[Test]
    public function runWorkflow_withInvalidWorkflowKey_throwsValidationError(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test Demand',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Workflow 'invalid_key' not found in configuration");

        // When
        $this->service->runWorkflow($uiDemand, 'invalid_key', []);
    }

    #[Test]
    public function runWorkflow_withMissingWorkflowDefinition_throwsValidationError(): void
    {
        // Given
        $organizeFilesWorkflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Organize Files',
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

        // Complete organize_files workflow first (dependency for extract_data)
        $organizeFilesWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $organizeFilesWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($organizeFilesWorkflowRun->id, [
            'workflow_type' => 'organize_files',
        ]);

        // No extract_data workflow definition exists

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Workflow definition 'Extract Service Dates' not found");

        // When
        $this->service->runWorkflow($uiDemand, 'extract_data', []);
    }

    #[Test]
    public function runWorkflow_withMissingInputFiles_throwsValidationError(): void
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

        // No input files attached

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Cannot run workflow 'extract_data'. Check dependencies and input requirements.");

        // When
        $this->service->runWorkflow($uiDemand, 'extract_data', []);
    }

    #[Test]
    public function runWorkflow_withUnmetDependencies_throwsValidationError(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
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
            'title'          => 'Test Demand',
        ]);

        // No completed extract_data workflow run (dependency not met)

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Cannot run workflow 'write_medical_summary'. Check dependencies and input requirements.");

        // When
        $this->service->runWorkflow($uiDemand, 'write_medical_summary', []);
    }

    #[Test]
    public function runWorkflow_withInstructionTemplate_appendsInstructions(): void
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
            'title'          => 'Test Demand',
        ]);

        $extractDataWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $extractDataWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($extractDataWorkflowRun->id, [
            'workflow_type' => 'extract_data',
        ]);

        $instructionTemplate = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'content' => 'Use a professional medical tone and include specific dates.',
        ]);

        // When
        $workflowRun = $this->service->runWorkflow($uiDemand, 'write_medical_summary', [
            'instruction_template_id' => $instructionTemplate->id,
            'additional_instructions' => 'Focus on critical injuries only.',
        ]);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        $this->assertEquals('write_medical_summary', $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type);
    }

    #[Test]
    public function handleUiDemandWorkflowComplete_withSuccessfulExtractDataWorkflow_updatesCorrectly(): void
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

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'extract_data']);

        $artifact      = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflowNode  = WorkflowNode::factory()->create(['workflow_definition_id' => $workflowDefinition->id]);
        $taskDef       = TaskDefinition::factory()->create(['task_runner_name' => 'test_processing_task']);
        $taskRun       = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        $taskRun->outputArtifacts()->attach($artifact->id);

        $outputTaskDef  = TaskDefinition::factory()->create(['task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME]);
        $outputNode     = WorkflowNode::factory()->create(['workflow_definition_id' => $workflowDefinition->id]);
        $outputTaskRun  = TaskRun::factory()->create([
            'task_definition_id' => $outputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);
        $outputProcess = TaskProcess::factory()->create(['task_run_id' => $outputTaskRun->id]);
        $outputProcess->inputArtifacts()->attach($artifact->id);
        $outputProcess->getRunner()->run();

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('extract_data_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
    }

    #[Test]
    public function handleUiDemandWorkflowComplete_withSuccessfulWriteMedicalSummaryWorkflow_attachesArtifactsToTeamObject(): void
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

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'write_medical_summary']);

        $artifact      = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Medical summary content',
        ]);
        $workflowNode  = WorkflowNode::factory()->create(['workflow_definition_id' => $workflowDefinition->id]);
        $taskDef       = TaskDefinition::factory()->create(['task_runner_name' => 'test_processing_task']);
        $taskRun       = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        $taskRun->outputArtifacts()->attach($artifact->id);

        $outputTaskDef  = TaskDefinition::factory()->create(['task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME]);
        $outputNode     = WorkflowNode::factory()->create(['workflow_definition_id' => $workflowDefinition->id]);
        $outputTaskRun  = TaskRun::factory()->create([
            'task_definition_id' => $outputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);
        $outputProcess = TaskProcess::factory()->create(['task_run_id' => $outputTaskRun->id]);
        $outputProcess->inputArtifacts()->attach($artifact->id);
        $outputProcess->getRunner()->run();

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('write_medical_summary_completed_at', $updatedDemand->metadata);

        // Verify artifact was attached to TeamObject with medical_summary category
        $medicalSummaries = $teamObject->fresh()->getArtifactsByCategory('medical_summary');
        $this->assertCount(1, $medicalSummaries);
        $this->assertEquals($artifact->id, $medicalSummaries->first()->id);
    }

    #[Test]
    public function handleUiDemandWorkflowComplete_withSuccessfulWriteDemandLetterWorkflow_attachesFilesToTeamObject(): void
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

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'write_demand_letter']);

        $artifact   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'demand_letter.gdoc',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        $workflowNode  = WorkflowNode::factory()->create(['workflow_definition_id' => $workflowDefinition->id]);
        $taskDef       = TaskDefinition::factory()->create(['task_runner_name' => 'test_processing_task']);
        $taskRun       = TaskRun::factory()->create([
            'task_definition_id' => $taskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        $taskRun->outputArtifacts()->attach($artifact->id);

        $outputTaskDef  = TaskDefinition::factory()->create(['task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME]);
        $outputNode     = WorkflowNode::factory()->create(['workflow_definition_id' => $workflowDefinition->id]);
        $outputTaskRun  = TaskRun::factory()->create([
            'task_definition_id' => $outputTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);
        $outputProcess = TaskProcess::factory()->create(['task_run_id' => $outputTaskRun->id]);
        $outputProcess->inputArtifacts()->attach($artifact->id);
        $outputProcess->getRunner()->run();

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $updatedDemand->metadata);

        // Verify artifact was attached to TeamObject with output_document category
        $outputArtifacts = $teamObject->fresh()->getArtifactsByCategory('output_document');
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact->id, $outputArtifacts->first()->id);

        // Verify the artifact has the stored file attached
        $this->assertEquals(1, $artifact->storedFiles->count());
        $this->assertEquals($storedFile->id, $artifact->storedFiles->first()->id);
    }

    #[Test]
    public function handleUiDemandWorkflowComplete_withFailedWorkflow_updatesCorrectly(): void
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

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'extract_data']);

        // When
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_FAILED, $updatedDemand->status);
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('failed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('error', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
    }

    #[Test]
    public function handleUiDemandWorkflowComplete_withNoMatchingDemand_doesNothing(): void
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
}
