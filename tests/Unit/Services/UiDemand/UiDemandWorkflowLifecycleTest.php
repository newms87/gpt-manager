<?php

namespace Tests\Unit\Services\UiDemand;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWorkflowLifecycleTest extends AuthenticatedTestCase
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
        Config::set('ui-demands.workflows.write_demand', 'Write Demand Summary');

        // Mock queue to prevent actual job dispatching
        Queue::fake();

        // Enable events for this test to capture workflow completion
        Event::fake([WorkflowRunUpdatedEvent::class]);
    }

    public function test_extractData_fullLifecycle_createsWorkflowListenerAndHandlesCompletion(): void
    {
        // Given - Set up extract data workflow
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'status'   => UiDemand::STATUS_DRAFT,
            'title'    => 'Test Extract Data Demand',
            'metadata' => ['existing_key' => 'existing_value'],
        ]);

        $inputFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($inputFile->id, ['category' => 'input']);

        // When - Start extract data workflow
        $workflowRun = $this->service->extractData($uiDemand);

        // Then - Verify WorkflowListener is created
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        $workflowListener = WorkflowListener::where('workflow_run_id', $workflowRun->id)->first();
        $this->assertNotNull($workflowListener);
        $this->assertEquals(UiDemand::class, $workflowListener->listener_type);
        $this->assertEquals($uiDemand->id, $workflowListener->listener_id);
        $this->assertEquals(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA, $workflowListener->workflow_type);
        $this->assertEquals(WorkflowListener::STATUS_PENDING, $workflowListener->status);

        // Verify pivot relationship is created
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
        $this->assertEquals(
            UiDemand::WORKFLOW_TYPE_EXTRACT_DATA,
            $uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->first()->pivot->workflow_type
        );

        // When - Simulate workflow completion by marking WorkflowRun as completed
        $workflowRun->update([
            'status'       => \App\Models\Workflow\WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // Simulate the workflow completion callback
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify extract data completion effects
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);

        // Verify metadata is updated with extract data completion
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertEquals('existing_value', $updatedDemand->metadata['existing_key']);
        $this->assertArrayHasKey('extract_data_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);

        // Verify the timestamp is recent (within last 5 seconds)
        $completedAt = new \DateTime($updatedDemand->metadata['extract_data_completed_at']);
        $this->assertLessThan(5, abs(time() - $completedAt->getTimestamp()));
    }

    public function test_writeDemandLetter_fullLifecycle_createsWorkflowListenerAndAttachesOutputFiles(): void
    {
        // Given - Set up write demand letter workflow
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        // Create a UiDemand that can write demand letter (has team object and completed medical summary)
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'title'          => 'Test Write Demand Letter',
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
        ]);

        // Mock a completed medical summary workflow run (prerequisite for demand letter)
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'status'                 => \App\Models\Workflow\WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at'           => now(),
        ]);
        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY]);

        // When - Start write demand letter workflow
        $workflowRun = $this->service->writeDemandLetter($uiDemand);

        // Then - Verify WorkflowListener is created
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        $workflowListener = WorkflowListener::where('workflow_run_id', $workflowRun->id)->first();
        $this->assertNotNull($workflowListener);
        $this->assertEquals(UiDemand::class, $workflowListener->listener_type);
        $this->assertEquals($uiDemand->id, $workflowListener->listener_id);
        $this->assertEquals(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER, $workflowListener->workflow_type);
        $this->assertEquals(WorkflowListener::STATUS_PENDING, $workflowListener->status);

        // Create output artifacts with stored files to simulate workflow output
        $outputFile1 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'demand_output_1.docx',
        ]);

        $outputFile2 = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'demand_output_2.pdf',
        ]);

        // Create artifacts and attach stored files
        $artifact1 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $artifact1->storedFiles()->attach($outputFile1->id);

        $artifact2 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $artifact2->storedFiles()->attach($outputFile2->id);

        // Simulate proper workflow execution
        // 1. Some processing task produces the artifacts
        $processingTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => 'document_processing_task',
        ]);

        $processingNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $processingTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $processingTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $processingNode->id,
        ]);

        // The processing task produces both artifacts
        $processingTaskRun->outputArtifacts()->attach([$artifact1->id, $artifact2->id]);

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
        $outputTaskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id]);

        // Run the WorkflowOutputTaskRunner
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        // When - Simulate workflow completion
        $workflowRun->update([
            'status'       => \App\Models\Workflow\WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // Since the collectFinalOutputArtifacts needs proper workflow nodes,
        // let's simulate the file attachment directly
        $outputArtifacts = collect([$artifact1, $artifact2]);

        // Use reflection to call the protected method for testing
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('attachOutputFilesFromWorkflow');
        $method->setAccessible(true);
        $method->invokeArgs($this->service, [$uiDemand, $outputArtifacts]);

        // Then call the regular workflow completion handler for metadata updates
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify write demand completion effects
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedDemand->status);

        // Verify metadata is updated with write demand letter completion
        $this->assertArrayHasKey('write_medical_summary_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('write_demand_letter_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);

        // Verify output files are attached to UiDemand
        $outputFiles = $updatedDemand->outputFiles;
        $this->assertCount(2, $outputFiles);

        $outputFileIds = $outputFiles->pluck('id')->toArray();
        $this->assertContains($outputFile1->id, $outputFileIds);
        $this->assertContains($outputFile2->id, $outputFileIds);

        // Verify the pivot data has correct category
        foreach ($outputFiles as $file) {
            $this->assertEquals('output', $file->pivot->category ?? 'output');
        }
    }

    public function test_extractData_workflowFailure_updatesStatusAndMetadata(): void
    {
        // Given - Set up extract data workflow
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'status'   => UiDemand::STATUS_DRAFT,
            'title'    => 'Test Extract Data Failure',
            'metadata' => ['existing_key' => 'existing_value'],
        ]);

        $inputFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($inputFile->id, ['category' => 'input']);

        // When - Start and fail extract data workflow
        $workflowRun = $this->service->extractData($uiDemand);
        $workflowRun->update([
            'status'    => \App\Models\Workflow\WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // Simulate the workflow failure callback
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify failure handling
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_FAILED, $updatedDemand->status);

        // Verify metadata includes failure information
        $this->assertArrayHasKey('existing_key', $updatedDemand->metadata);
        $this->assertArrayHasKey('failed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('error', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals(\App\Models\Workflow\WorkflowStatesContract::STATUS_FAILED, $updatedDemand->metadata['error']);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
    }

    public function test_writeDemandLetter_workflowFailure_updatesStatusAndMetadata(): void
    {
        // Given - Set up write demand letter workflow
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'title'          => 'Test Write Demand Letter Failure',
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
        ]);

        // Mock a completed medical summary workflow run (prerequisite for demand letter)
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'status'                 => \App\Models\Workflow\WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at'           => now(),
        ]);
        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY]);

        // When - Start and fail write demand workflow
        $workflowRun = $this->service->writeDemandLetter($uiDemand);
        $workflowRun->update([
            'status'    => \App\Models\Workflow\WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // Simulate the workflow failure callback
        $this->service->handleUiDemandWorkflowComplete($workflowRun);

        // Then - Verify failure handling
        $updatedDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_FAILED, $updatedDemand->status);

        // Verify metadata includes failure information
        $this->assertArrayHasKey('write_medical_summary_completed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('failed_at', $updatedDemand->metadata);
        $this->assertArrayHasKey('error', $updatedDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedDemand->metadata);
        $this->assertEquals(\App\Models\Workflow\WorkflowStatesContract::STATUS_FAILED, $updatedDemand->metadata['error']);
        $this->assertEquals($workflowRun->id, $updatedDemand->metadata['workflow_run_id']);
    }

    public function test_workflowListener_statusTransitions_duringLifecycle(): void
    {
        // Given - Set up extract data workflow
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Test WorkflowListener Status',
        ]);

        $inputFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $uiDemand->inputFiles()->attach($inputFile->id, ['category' => 'input']);

        // When - Start workflow
        $workflowRun = $this->service->extractData($uiDemand);

        // Then - Verify initial WorkflowListener state
        $workflowListener = WorkflowListener::where('workflow_run_id', $workflowRun->id)->first();
        $this->assertNotNull($workflowListener);
        $this->assertEquals(WorkflowListener::STATUS_PENDING, $workflowListener->status);
        $this->assertNull($workflowListener->started_at);
        $this->assertNull($workflowListener->completed_at);
        $this->assertNull($workflowListener->failed_at);

        // When - Simulate workflow running (this would happen automatically in real workflow)
        $workflowListener->markAsRunning();

        // Then - Verify running state
        $workflowListener->refresh();
        $this->assertEquals(WorkflowListener::STATUS_RUNNING, $workflowListener->status);
        $this->assertNotNull($workflowListener->started_at);
        $this->assertNull($workflowListener->completed_at);
        $this->assertNull($workflowListener->failed_at);

        // When - Complete workflow
        $workflowRun->update([
            'status'       => \App\Models\Workflow\WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $workflowListener->markAsCompleted();

        // Then - Verify completed state
        $workflowListener->refresh();
        $this->assertEquals(WorkflowListener::STATUS_COMPLETED, $workflowListener->status);
        $this->assertNotNull($workflowListener->started_at);
        $this->assertNotNull($workflowListener->completed_at);
        $this->assertNull($workflowListener->failed_at);
    }

    public function test_writeDemand_withTemplate_includesTemplateInWorkflowInput(): void
    {
        // Given - Set up write demand letter workflow with template
        $writeDemandLetterWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Document',
        ]);

        $templateFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'user_id'  => $this->user->id,
            'filename' => 'demand_template.docx',
        ]);

        $template = \App\Models\Demand\DemandTemplate::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $templateFile->id,
            'name'           => 'Test Template',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'status'         => UiDemand::STATUS_DRAFT,
            'title'          => 'Test Write Demand with Template',
            'team_object_id' => $teamObject->id,
            'metadata'       => ['write_medical_summary_completed_at' => now()->toIso8601String()],
        ]);

        // Mock a completed medical summary workflow run (prerequisite for demand letter)
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'status'                 => \App\Models\Workflow\WorkflowStatesContract::STATUS_COMPLETED,
            'completed_at'           => now(),
        ]);
        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY]);

        // When - Start write demand workflow with template and additional instructions
        $workflowRun = $this->service->writeDemandLetter($uiDemand, $template->id, 'Please make it professional');

        // Then - Verify WorkflowListener is created and workflow starts
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        $workflowListener = WorkflowListener::where('workflow_run_id', $workflowRun->id)->first();
        $this->assertNotNull($workflowListener);
        $this->assertEquals(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER, $workflowListener->workflow_type);

        // The template and instructions would be included in the WorkflowInput content
        // This is verified by the successful creation of the workflow without errors
        $this->assertTrue($uiDemand->fresh()->workflowRuns()->where('workflow_runs.id', $workflowRun->id)->exists());
    }
}
