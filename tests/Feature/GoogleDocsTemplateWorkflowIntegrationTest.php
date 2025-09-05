<?php

namespace Tests\Feature;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Events\WorkflowRunUpdatedEvent;
use App\Listeners\WorkflowListenerCompletedListener;
use App\Models\Agent\Agent;
use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\Traits\SetUpTeamTrait;

class GoogleDocsTemplateWorkflowIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected UiDemandWorkflowService           $uiDemandWorkflowService;
    protected WorkflowListenerCompletedListener $workflowListener;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->uiDemandWorkflowService = app(UiDemandWorkflowService::class);
        $this->workflowListener        = app(WorkflowListenerCompletedListener::class);

        // Configure test-model for testing
        Config::set('ai.models.test-model', [
            'api'     => TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
        ]);

        // Set up workflow configuration
        Config::set('ui-demands.workflows.write_demand', 'Write Demand Summary');

        // Mock queue to prevent actual job dispatching
        Queue::fake();
        Event::fake([WorkflowRunUpdatedEvent::class]);
    }

    public function test_completeWorkflow_googleDocsTemplateTaskRunner_to_uiDemandFileAttachment(): void
    {
        // Given - Set up the complete workflow chain

        // 1. Create GoogleDocsTemplateTaskRunner and related setup
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'agent_id'         => $agent->id,
            'task_runner_name' => GoogleDocsTemplateTaskRunner::RUNNER_NAME,
        ]);

        // 2. Create UiDemand with template
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
            'title'   => 'Integration Test Demand',
        ]);

        // 3. Create template StoredFile
        $templateStoredFile = new StoredFile([
            'disk'     => 'google',
            'filename' => 'Template Document',
            'url'      => 'https://docs.google.com/document/d/template-doc-123/edit',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // Set team_id and user_id separately since they're not fillable
        $templateStoredFile->team_id = $this->user->currentTeam->id;
        $templateStoredFile->user_id = $this->user->id;
        $templateStoredFile->save();

        // 4. Create workflow definition and setup
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // 5. Create workflow run and task run
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'running',
        ]);

        $taskRun = TaskRun::factory()->create([
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskProcess = $taskRun->taskProcesses()->create([
            'name'   => 'Google Docs Template Process',
            'status' => 'pending',
        ]);

        // 6. Create input artifact with template reference
        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $templateStoredFile->id,
                'demand_data'             => [
                    'title'       => 'Integration Test Demand',
                    'client_name' => 'Test Client',
                    'amount'      => '$10,000',
                ],
            ],
        ]);

        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // 7. Create workflow listener for UiDemand
        WorkflowListener::factory()->create([
            'team_id'         => $this->user->currentTeam->id,
            'workflow_run_id' => $workflowRun->id,
            'listener_type'   => UiDemand::class,
            'listener_id'     => $uiDemand->id,
            'workflow_type'   => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        // 8. Mock GoogleDocsApi to simulate document creation
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('extractTemplateVariables')
                ->andReturn(['client_name', 'title', 'amount']);

            $mock->shouldReceive('createDocumentFromTemplate')
                ->andReturn([
                    'document_id' => 'generated-doc-456',
                    'title'       => 'Generated Demand Document',
                    'url'         => 'https://docs.google.com/document/d/generated-doc-456/edit',
                    'created_at'  => '2023-01-01T12:00:00Z',
                ]);
        });

        // When - Execute the GoogleDocsTemplateTaskRunner

        // Create mock agent response artifact
        $agentResponseArtifact = Artifact::factory()->create([
            'team_id'      => $taskDefinition->team_id,
            'text_content' => json_encode([
                'title'     => 'Generated Demand Document',
                'variables' => [
                    'client_name' => 'Test Client',
                    'title'       => 'Integration Test Demand',
                    'amount'      => '$10,000',
                ],
                'reasoning' => 'Mapped variables from input data',
            ]),
        ]);

        // Create a partial mock that only mocks the runAgentThread method
        $runner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['runAgentThread'])
            ->getMock();

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->willReturn($agentResponseArtifact);

        $runner->setTaskRun($taskRun)
            ->setTaskProcess($taskProcess);

        $runner->run();

        // After GoogleDocsTemplateTaskRunner completes, run WorkflowOutputTaskRunner to collect outputs
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

        // Get the artifacts created by GoogleDocsTemplateTaskRunner
        $generatedArtifacts = $taskRun->outputArtifacts()->get();

        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);

        // Pass the generated artifacts to the output task
        foreach($generatedArtifacts as $artifact) {
            $outputTaskProcess->inputArtifacts()->attach($artifact->id);
        }

        // Run the WorkflowOutputTaskRunner to collect workflow outputs
        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        // Simulate workflow completion
        $workflowRun->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Trigger the workflow completion event manually (since we're faking events)
        $workflowRunUpdatedEvent = new WorkflowRunUpdatedEvent($workflowRun, 'completed');
        $this->workflowListener->handle($workflowRunUpdatedEvent);

        // Then - Verify the complete chain worked correctly

        // 1. Verify GoogleDocsTemplateTaskRunner created output artifact with StoredFile
        $outputArtifacts = $taskRun->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);

        $outputArtifact = $outputArtifacts->first();
        $this->assertEquals('Generated Google Doc: Generated Demand Document', $outputArtifact->name);
        $this->assertArrayHasKey('google_doc_url', $outputArtifact->meta);
        $this->assertEquals('https://docs.google.com/document/d/generated-doc-456/edit', $outputArtifact->meta['google_doc_url']);

        // 2. Verify StoredFile was created and attached to artifact
        $this->assertCount(1, $outputArtifact->storedFiles);
        $generatedStoredFile = $outputArtifact->storedFiles->first();
        $this->assertEquals('Generated Demand Document.gdoc', $generatedStoredFile->filename);
        $this->assertEquals('https://docs.google.com/document/d/generated-doc-456/edit', $generatedStoredFile->url);
        $this->assertEquals('external', $generatedStoredFile->disk);
        $this->assertEquals('google_docs', $generatedStoredFile->meta['type']);
        $this->assertEquals('generated-doc-456', $generatedStoredFile->meta['document_id']);

        // 3. Verify workflow listener processed the completion correctly
        $updatedUiDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_DRAFT, $updatedUiDemand->status);
        $this->assertArrayHasKey('write_demand_completed_at', $updatedUiDemand->metadata);
        $this->assertArrayHasKey('workflow_run_id', $updatedUiDemand->metadata);
        $this->assertEquals($workflowRun->id, $updatedUiDemand->metadata['workflow_run_id']);

        // 4. Verify StoredFile was reused and attached to UiDemand as output file
        $outputFiles = $updatedUiDemand->outputFiles;
        $this->assertCount(1, $outputFiles);

        $attachedOutputFile = $outputFiles->first();
        $this->assertEquals($generatedStoredFile->id, $attachedOutputFile->id); // Same StoredFile instance reused
        $this->assertEquals('Generated Demand Document.gdoc', $attachedOutputFile->filename);

        // 5. Verify database relationships are correct
        $this->assertDatabaseHas('stored_file_storables', [
            'stored_file_id' => $generatedStoredFile->id,
            'storable_type'  => 'App\\Models\\Demand\\UiDemand',
            'storable_id'    => $uiDemand->id,
            'category'       => 'output',
        ]);

        // 6. Verify no duplicate StoredFiles were created
        $totalGoogleDocsFiles = StoredFile::where('team_id', $this->user->currentTeam->id)
            ->where('url', 'like', '%docs.google.com%')
            ->count();
        $this->assertEquals(2, $totalGoogleDocsFiles); // Template + Generated document
    }

    public function test_multipleWriteDemandRuns_reuseStoredFilesCorrectly(): void
    {
        // Given - Set up for multiple workflow runs using the same generated document

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        // Create an existing StoredFile (as if from previous workflow run)
        $existingStoredFile = new StoredFile([
            'disk'     => 'external',
            'filename' => 'Reused Document.gdoc',
            'url'      => 'https://docs.google.com/document/d/reused-doc-789/edit',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
            'meta'     => [
                'type'        => 'google_docs',
                'document_id' => 'reused-doc-789',
            ],
        ]);

        // Set team_id and user_id separately since they're not fillable
        $existingStoredFile->team_id = $this->user->currentTeam->id;
        $existingStoredFile->user_id = $this->user->id;
        $existingStoredFile->save();

        // First workflow run
        $firstWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $firstArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $firstArtifact->storedFiles()->attach($existingStoredFile->id);

        $firstWorkflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate proper workflow execution for reuse test
        // 1. Document generation task produces the artifact
        $docGenTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => GoogleDocsTemplateTaskRunner::class,
        ]);

        $docGenTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $docGenTaskDef->id,
            'workflow_run_id'    => $firstWorkflowRun->id,
            'workflow_node_id'   => $firstWorkflowNode->id,
        ]);

        $docGenTaskRun->outputArtifacts()->attach($firstArtifact->id);

        // 2. Workflow output task collects final outputs
        $workflowOutputTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => \App\Services\Task\Runners\WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        $outputNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $outputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id,
            'workflow_run_id'    => $firstWorkflowRun->id,
            'workflow_node_id'   => $outputNode->id,
        ]);

        $outputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $outputTaskRun->id,
        ]);
        $outputTaskProcess->inputArtifacts()->attach($firstArtifact->id);

        $workflowOutputRunner = $outputTaskProcess->getRunner();
        $workflowOutputRunner->run();

        $uiDemand->workflowRuns()->attach($firstWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        // Second workflow run (reusing the same document)
        $secondWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'completed',
            'completed_at'           => now(),
        ]);

        $secondArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $secondArtifact->storedFiles()->attach($existingStoredFile->id); // Same StoredFile

        $secondWorkflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate second workflow execution
        // 1. Document generation task produces the artifact
        $secondDocGenTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $docGenTaskDef->id, // Reuse same task definition
            'workflow_run_id'    => $secondWorkflowRun->id,
            'workflow_node_id'   => $secondWorkflowNode->id,
        ]);

        $secondDocGenTaskRun->outputArtifacts()->attach($secondArtifact->id);

        // 2. Workflow output task collects final outputs
        $secondOutputNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $secondOutputTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowOutputTaskDef->id, // Reuse same task definition
            'workflow_run_id'    => $secondWorkflowRun->id,
            'workflow_node_id'   => $secondOutputNode->id,
        ]);

        $secondOutputTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $secondOutputTaskRun->id,
        ]);
        $secondOutputTaskProcess->inputArtifacts()->attach($secondArtifact->id);

        // Run the WorkflowOutputTaskRunner
        $secondWorkflowOutputRunner = $secondOutputTaskProcess->getRunner();
        $secondWorkflowOutputRunner->run();

        $uiDemand->workflowRuns()->attach($secondWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        // Create WorkflowListeners for both workflow runs
        WorkflowListener::factory()->create([
            'team_id'         => $this->user->currentTeam->id,
            'workflow_run_id' => $firstWorkflowRun->id,
            'listener_type'   => UiDemand::class,
            'listener_id'     => $uiDemand->id,
            'workflow_type'   => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        WorkflowListener::factory()->create([
            'team_id'         => $this->user->currentTeam->id,
            'workflow_run_id' => $secondWorkflowRun->id,
            'listener_type'   => UiDemand::class,
            'listener_id'     => $uiDemand->id,
            'workflow_type'   => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When - Process both workflow completions
        $firstEvent = new WorkflowRunUpdatedEvent($firstWorkflowRun, 'completed');
        $this->workflowListener->handle($firstEvent);

        $secondEvent = new WorkflowRunUpdatedEvent($secondWorkflowRun, 'completed');
        $this->workflowListener->handle($secondEvent);

        // Then - Verify StoredFile reuse without duplication
        $outputFiles = $uiDemand->fresh()->outputFiles;
        $this->assertCount(1, $outputFiles); // Should only have one file, not duplicated

        $attachedFile = $outputFiles->first();
        $this->assertEquals($existingStoredFile->id, $attachedFile->id);
        $this->assertEquals('Reused Document.gdoc', $attachedFile->filename);

        // Verify there's only one pivot table record for this file
        $pivotRecords = \DB::table('stored_file_storables')
            ->where('stored_file_id', $existingStoredFile->id)
            ->where('storable_type', 'App\\Models\\Demand\\UiDemand')
            ->where('storable_id', $uiDemand->id)
            ->where('category', 'output')
            ->count();

        $this->assertEquals(1, $pivotRecords);

        // Verify no duplicate StoredFiles were created
        $totalStoredFiles = StoredFile::where('team_id', $this->user->currentTeam->id)->count();
        $this->assertEquals(1, $totalStoredFiles); // Only the original file should exist
    }

    public function test_workflowFailure_doesNotAttachFiles(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $failedWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => 'failed',
            'failed_at'              => now(),
        ]);

        $uiDemand->workflowRuns()->attach($failedWorkflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        // Create WorkflowListener to connect the workflow run to the UiDemand
        WorkflowListener::factory()->create([
            'team_id'         => $this->user->currentTeam->id,
            'workflow_run_id' => $failedWorkflowRun->id,
            'listener_type'   => UiDemand::class,
            'listener_id'     => $uiDemand->id,
            'workflow_type'   => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $failedEvent = new WorkflowRunUpdatedEvent($failedWorkflowRun, 'failed');
        $this->workflowListener->handle($failedEvent);

        // Then
        $updatedUiDemand = $uiDemand->fresh();
        $this->assertEquals(UiDemand::STATUS_FAILED, $updatedUiDemand->status);
        $this->assertCount(0, $updatedUiDemand->outputFiles); // No files should be attached on failure
        $this->assertArrayHasKey('failed_at', $updatedUiDemand->metadata);
        $this->assertArrayHasKey('error', $updatedUiDemand->metadata);
    }

    public function test_teamBasedAccessControl_ensuresIsolation(): void
    {
        // Given - Create two teams with separate data
        $otherTeam = \App\Models\Team\Team::factory()->create();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $otherTeamDemand = UiDemand::factory()->create([
            'team_id' => $otherTeam->id,
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

        // Create StoredFile for current team
        $teamStoredFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'team-document.gdoc',
        ]);

        // Create StoredFile for other team
        $otherTeamStoredFile = StoredFile::factory()->create([
            'team_id'  => $otherTeam->id,
            'filename' => 'other-team-document.gdoc',
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $artifact->storedFiles()->attach($teamStoredFile->id);

        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Simulate proper workflow execution
        // 1. Document generation task produces the artifact
        $docGenTaskDef = TaskDefinition::factory()->create([
            'task_runner_name' => GoogleDocsTemplateTaskRunner::class,
        ]);

        $docGenTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $docGenTaskDef->id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
        ]);

        // The document generation task produces the artifact
        $docGenTaskRun->outputArtifacts()->attach($artifact->id);

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

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);

        // Create WorkflowListener to connect the workflow run to the UiDemand
        WorkflowListener::factory()->create([
            'team_id'         => $this->user->currentTeam->id,
            'workflow_run_id' => $workflowRun->id,
            'listener_type'   => UiDemand::class,
            'listener_id'     => $uiDemand->id,
            'workflow_type'   => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // When
        $event = new WorkflowRunUpdatedEvent($workflowRun, 'completed');
        $this->workflowListener->handle($event);

        // Then - Verify team isolation
        $updatedUiDemand = $uiDemand->fresh();
        $outputFiles     = $updatedUiDemand->outputFiles;

        $this->assertCount(1, $outputFiles);
        $this->assertEquals($teamStoredFile->id, $outputFiles->first()->id);
        $this->assertEquals($this->user->currentTeam->id, $outputFiles->first()->team_id);

        // Verify other team's data is not affected
        $this->assertCount(0, $otherTeamDemand->fresh()->outputFiles);
    }

    /**
     * Mock agent variable mapping response for GoogleDocsTemplateTaskRunner
     */
    protected function mockAgentVariableMapping(GoogleDocsTemplateTaskRunner $runner, TaskDefinition $taskDefinition): void
    {
        // Create a mock agent response artifact
        $agentResponseArtifact = Artifact::factory()->create([
            'team_id'      => $taskDefinition->team_id,
            'text_content' => json_encode([
                'title'     => 'Generated Demand Document',
                'variables' => [
                    'client_name' => 'Test Client',
                    'title'       => 'Integration Test Demand',
                    'amount'      => '$10,000',
                ],
                'reasoning' => 'Mapped variables from input data',
            ]),
        ]);

        // Use reflection to mock the runAgentThread method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('runAgentThread');
        $method->setAccessible(true);

        // Replace the runner with a mock that returns our artifact
        $mockRunner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['runAgentThread'])
            ->getMock();

        $mockRunner->method('runAgentThread')
            ->willReturn($agentResponseArtifact);

        // Copy properties from original runner to mock
        $taskRunProperty = $reflection->getProperty('taskRun');
        $taskRunProperty->setAccessible(true);
        $taskRunProperty->setValue($mockRunner, $taskRunProperty->getValue($runner));

        $taskProcessProperty = $reflection->getProperty('taskProcess');
        $taskProcessProperty->setAccessible(true);
        $taskProcessProperty->setValue($mockRunner, $taskProcessProperty->getValue($runner));

        // Replace the original runner with our mock
        $reflection = new \ReflectionClass($runner);
        foreach($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($runner);
            $property->setValue($mockRunner, $value);
        }

        // Update the runner reference
        $runner = $mockRunner;
    }
}
