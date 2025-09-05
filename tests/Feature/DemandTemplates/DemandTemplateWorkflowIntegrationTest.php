<?php

namespace Tests\Feature\DemandTemplates;

use App\Models\Demand\DemandTemplate;
use App\Models\Demand\UiDemand;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use App\Services\UiDemand\UiDemandWorkflowService;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplateWorkflowIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected UiDemandWorkflowService $workflowService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->workflowService = app(UiDemandWorkflowService::class);

        // Set up required workflow configurations
        config([
            'ui-demands.workflows.extract_data' => 'Extract Service Dates',
            'ui-demands.workflows.write_demand' => 'Write Demand Summary',
        ]);
    }

    public function test_writeDemand_withTemplate_passesTemplateIdToWorkflow(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
            'name'           => 'Test Template',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'team_object_id' => $teamObject->id,
            'status'         => UiDemand::STATUS_COMPLETED,
            'metadata'       => [
                'extract_data_completed_at' => now()->toIso8601String(),
            ],
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

        // When
        $workflowRun = $this->workflowService->writeDemand(
            $uiDemand,
            $template->id,
            'Additional test instructions'
        );

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Verify the workflow was created with template data
        $this->assertDatabaseHas('ui_demand_workflow_runs', [
            'ui_demand_id'    => $uiDemand->id,
            'workflow_run_id' => $workflowRun->id,
            'workflow_type'   => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // Verify workflow input contains template information
        $startingNode   = $workflowRun->workflowDefinition->startingWorkflowNodes->first();
        $inputArtifacts = $workflowRun->collectInputArtifactsForNode($startingNode);
        $this->assertCount(1, $inputArtifacts);

        $inputArtifact = $inputArtifacts->first();
        $this->assertNotNull($inputArtifact->json_content);
        $this->assertEquals($template->stored_file_id, $inputArtifact->json_content['template_stored_file_id']);
        $this->assertEquals('Additional test instructions', $inputArtifact->json_content['additional_instructions']);
    }

    public function test_writeDemand_withoutTemplate_doesNotIncludeTemplateData(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'team_object_id' => $teamObject->id,
            'status'         => UiDemand::STATUS_COMPLETED,
            'metadata'       => [
                'extract_data_completed_at' => now()->toIso8601String(),
            ],
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

        // When
        $workflowRun = $this->workflowService->writeDemand($uiDemand);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Verify workflow input does not contain template information
        $startingNode   = $workflowRun->workflowDefinition->startingWorkflowNodes->first();
        $inputArtifacts = $workflowRun->collectInputArtifactsForNode($startingNode);
        $this->assertCount(1, $inputArtifacts);

        $inputArtifact = $inputArtifacts->first();
        $this->assertArrayNotHasKey('template_stored_file_id', $inputArtifact->json_content ?? []);
        $this->assertArrayNotHasKey('additional_instructions', $inputArtifact->json_content ?? []);
    }

    public function test_writeDemand_withAdditionalInstructionsOnly_includesInstructions(): void
    {
        // Given
        $extractDataWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Extract Service Dates',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Summary',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'team_object_id' => $teamObject->id,
            'status'         => UiDemand::STATUS_COMPLETED,
            'metadata'       => [
                'extract_data_completed_at' => now()->toIso8601String(),
            ],
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

        // When
        $workflowRun = $this->workflowService->writeDemand(
            $uiDemand,
            null,
            'Custom instructions without template'
        );

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Verify workflow input contains instructions but no template
        $startingNode   = $workflowRun->workflowDefinition->startingWorkflowNodes->first();
        $inputArtifacts = $workflowRun->collectInputArtifactsForNode($startingNode);
        $this->assertCount(1, $inputArtifacts);

        $inputArtifact = $inputArtifacts->first();
        $this->assertArrayNotHasKey('template_stored_file_id', $inputArtifact->json_content ?? []);
        $this->assertEquals('Custom instructions without template', $inputArtifact->json_content['additional_instructions']);
    }

    public function test_googleDocsTemplateTaskRunner_extractsGoogleDocIdFromStoredFile(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun        = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess    = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);

        $runner = GoogleDocsTemplateTaskRunner::make()
            ->setTaskRun($taskRun)
            ->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('extractGoogleDocIdFromStoredFile');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($runner, $storedFile);

        // Then
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result);
    }

    public function test_googleDocsTemplateTaskRunner_extractsIdFromPlainUrl(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun        = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess    = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);

        $runner = GoogleDocsTemplateTaskRunner::make()
            ->setTaskRun($taskRun)
            ->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('extractGoogleDocIdFromStoredFile');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($runner, $storedFile);

        // Then
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result);
    }

    public function test_googleDocsTemplateTaskRunner_withInvalidUrl_returnsNull(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://example.com/not-a-google-doc',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun        = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess    = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);

        $runner = GoogleDocsTemplateTaskRunner::make()
            ->setTaskRun($taskRun)
            ->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('extractGoogleDocIdFromStoredFile');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($runner, $storedFile);

        // Then
        $this->assertNull($result);
    }

    public function test_googleDocsTemplateTaskRunner_createsOutputArtifact(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun        = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess    = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);

        $runner = GoogleDocsTemplateTaskRunner::make()
            ->setTaskRun($taskRun)
            ->setTaskProcess($taskProcess);

        $newDocument = [
            'document_id' => 'test-doc-id',
            'url'         => 'https://docs.google.com/document/d/test-doc-id/edit',
            'title'       => 'Test Document',
            'created_at'  => now()->toIsoString(),
        ];

        $refinedMapping = [
            'title'     => 'Test Document',
            'variables' => [
                'client_name' => 'John Doe',
                'date'        => '2024-01-01',
            ],
            'reasoning' => 'Mapped based on available data',
        ];

        // When - Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('createOutputArtifact');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $newDocument, $refinedMapping);

        // Then
        $this->assertInstanceOf(Artifact::class, $result);
        $this->assertStringContainsString('Generated Google Doc: Test Document', $result->name);
        $this->assertStringContainsString('Successfully created Google Docs document', $result->text_content);
        $this->assertEquals($newDocument['url'], $result->meta['google_doc_url']);
        $this->assertEquals($newDocument['document_id'], $result->meta['google_doc_id']);
        $this->assertEquals($refinedMapping['variables'], $result->meta['variable_mapping']);
    }
}
