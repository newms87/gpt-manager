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
            'ui-demands.workflows.extract_data'          => 'Extract Service Dates',
            'ui-demands.workflows.write_medical_summary' => 'Write Medical Summary',
            'ui-demands.workflows.write_demand_letter'   => 'Write Demand Letter',
        ]);
    }

    public function test_writeDemandLetter_withTemplate_passesTemplateIdToWorkflow(): void
    {
        // Given
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
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
                'write_medical_summary_completed_at' => now()->toIso8601String(),
            ],
        ]);

        // Create completed medical summary workflow run (prerequisite for demand letter)
        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => 'write_medical_summary',
        ]);

        // When
        $workflowRun = $this->workflowService->runWorkflow($uiDemand, 'write_demand_letter', [
            'output_template_id'      => $template->id,
            'additional_instructions' => 'Additional test instructions',
        ]);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);

        // Verify the workflow was created with template data
        $this->assertDatabaseHas('ui_demand_workflow_runs', [
            'ui_demand_id'    => $uiDemand->id,
            'workflow_run_id' => $workflowRun->id,
            'workflow_type'   => 'write_demand_letter',
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

    public function test_writeDemandLetter_withoutTemplate_doesNotIncludeTemplateData(): void
    {
        // Given
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
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
                'write_medical_summary_completed_at' => now()->toIso8601String(),
            ],
        ]);

        // Create completed medical summary workflow run (prerequisite for demand letter)
        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => 'write_medical_summary',
        ]);

        // When
        $workflowRun = $this->workflowService->runWorkflow($uiDemand, 'write_demand_letter');

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

    public function test_writeDemandLetter_withAdditionalInstructionsOnly_includesInstructions(): void
    {
        // Given
        $medicalSummaryWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Medical Summary',
        ]);

        $writeDemandWorkflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Write Demand Letter',
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
                'write_medical_summary_completed_at' => now()->toIso8601String(),
            ],
        ]);

        // Create completed medical summary workflow run (prerequisite for demand letter)
        $medicalSummaryWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $medicalSummaryWorkflowDefinition->id,
            'completed_at'           => now(),
        ]);

        $uiDemand->workflowRuns()->attach($medicalSummaryWorkflowRun->id, [
            'workflow_type' => 'write_medical_summary',
        ]);

        // When
        $workflowRun = $this->workflowService->runWorkflow($uiDemand, 'write_demand_letter', [
            'additional_instructions' => 'Custom instructions without template',
        ]);

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

        $resolution = [
            'values' => [
                'client_name' => 'John Doe',
                'date'        => '2024-01-01',
            ],
            'title' => 'Test Document',
        ];

        // When - Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('createOutputArtifact');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $newDocument, $resolution);

        // Then
        $this->assertInstanceOf(Artifact::class, $result);
        $this->assertStringContainsString('Generated Google Doc: Test Document', $result->name);
        $this->assertStringContainsString('Successfully created Google Docs document', $result->text_content);
        $this->assertEquals($newDocument['url'], $result->meta['google_doc_url']);
        $this->assertEquals($newDocument['document_id'], $result->meta['google_doc_id']);
        $this->assertEquals($resolution['values'], $result->meta['variable_mapping']);
        $this->assertEquals($resolution['title'], $result->meta['resolved_title']);
    }
}
