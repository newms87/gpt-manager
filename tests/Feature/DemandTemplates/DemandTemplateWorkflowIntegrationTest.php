<?php

namespace Tests\Feature\DemandTemplates;

use App\Models\DemandTemplate;
use App\Models\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplateWorkflowIntegrationTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected UiDemandWorkflowService $workflowService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->workflowService = app(UiDemandWorkflowService::class);
    }

    public function test_writeDemand_withTemplate_passesTemplateIdToWorkflow(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => 'https://docs.google.com/document/d/test123/edit',
        ]);
        
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
            'name' => 'Test Template',
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'status' => UiDemand::STATUS_COMPLETED,
        ]);

        // When
        $workflowRun = $this->workflowService->writeDemand(
            $uiDemand,
            $template->stored_file_id,
            'Additional test instructions'
        );

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        
        // Verify the workflow was created with template data
        $this->assertDatabaseHas('ui_demand_workflow_runs', [
            'ui_demand_id' => $uiDemand->id,
            'workflow_run_id' => $workflowRun->id,
            'workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND,
        ]);

        // Verify workflow input contains template information
        $workflowInputs = $workflowRun->inputs;
        $this->assertCount(1, $workflowInputs);
        
        $inputArtifact = $workflowInputs->first()->toArtifact();
        $this->assertNotNull($inputArtifact->json_content);
        $this->assertEquals($template->stored_file_id, $inputArtifact->json_content['template_stored_file_id']);
        $this->assertEquals('Additional test instructions', $inputArtifact->json_content['additional_instructions']);
    }

    public function test_writeDemand_withoutTemplate_doesNotIncludeTemplateData(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'status' => UiDemand::STATUS_COMPLETED,
        ]);

        // When
        $workflowRun = $this->workflowService->writeDemand($uiDemand);

        // Then
        $this->assertInstanceOf(WorkflowRun::class, $workflowRun);
        
        // Verify workflow input does not contain template information
        $workflowInputs = $workflowRun->inputs;
        $this->assertCount(1, $workflowInputs);
        
        $inputArtifact = $workflowInputs->first()->toArtifact();
        $this->assertArrayNotHasKey('template_stored_file_id', $inputArtifact->json_content ?? []);
        $this->assertArrayNotHasKey('additional_instructions', $inputArtifact->json_content ?? []);
    }

    public function test_writeDemand_withAdditionalInstructionsOnly_includesInstructions(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'status' => UiDemand::STATUS_COMPLETED,
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
        $workflowInputs = $workflowRun->inputs;
        $this->assertCount(1, $workflowInputs);
        
        $inputArtifact = $workflowInputs->first()->toArtifact();
        $this->assertArrayNotHasKey('template_stored_file_id', $inputArtifact->json_content ?? []);
        $this->assertEquals('Custom instructions without template', $inputArtifact->json_content['additional_instructions']);
    }

    public function test_googleDocsTemplateTaskRunner_findsStoredFileId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit',
        ]);

        // Create artifact with template_stored_file_id
        $artifactData = [
            'template_stored_file_id' => $storedFile->id,
            'additional_instructions' => 'Test instructions',
            'other_data' => 'Some other data',
        ];

        // When - Mock the extraction process
        $taskDefinition = \App\Models\Task\TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun = \App\Models\Task\TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);
        
        $runner = new \App\Services\Task\Runners\GoogleDocsTemplateTaskRunner($taskDefinition, $taskRun, $taskProcess);
        $artifacts = collect([
            (object) [
                'json_content' => $artifactData,
                'meta' => [],
            ]
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result);
    }

    public function test_googleDocsTemplateTaskRunner_extractsIdFromJustId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
        ]);

        // Create artifact with template_stored_file_id
        $artifactData = [
            'template_stored_file_id' => $storedFile->id,
        ];

        // When - Mock the extraction process
        $taskDefinition = \App\Models\Task\TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun = \App\Models\Task\TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);
        
        $runner = new \App\Services\Task\Runners\GoogleDocsTemplateTaskRunner($taskDefinition, $taskRun, $taskProcess);
        $artifacts = collect([
            (object) [
                'json_content' => $artifactData,
                'meta' => [],
            ]
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result);
    }

    public function test_googleDocsTemplateTaskRunner_withNonExistentStoredFile_returnsNull(): void
    {
        // Given
        $artifactData = [
            'template_stored_file_id' => 'non-existent-uuid',
        ];

        // When - Mock the extraction process
        $taskDefinition = \App\Models\Task\TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun = \App\Models\Task\TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);
        $taskProcess = $taskRun->taskProcesses()->create(['name' => 'Test Process', 'status' => 'pending']);
        
        $runner = new \App\Services\Task\Runners\GoogleDocsTemplateTaskRunner($taskDefinition, $taskRun, $taskProcess);
        $artifacts = collect([
            (object) [
                'json_content' => $artifactData,
                'meta' => [],
            ]
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then
        $this->assertNull($result);
    }

    public function test_googleDocsTemplateTaskRunner_collectsTemplateData(): void
    {
        // Given
        $runner = new \App\Services\Task\Runners\GoogleDocsTemplateTaskRunner(null, null, null);
        $artifacts = collect([
            (object) [
                'json_content' => [
                    'template_stored_file_id' => 'file-123',
                    'additional_instructions' => 'Test instructions',
                    'demand_id' => 456,
                    'title' => 'Test Demand',
                ],
                'meta' => [
                    'category' => 'insurance',
                    'priority' => 'high',
                ],
            ]
        ]);

        // When - Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('collectTemplateData');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then
        $expectedData = [
            'additional_instructions' => 'Test instructions',
            'demand_id' => 456,
            'title' => 'Test Demand',
            'category' => 'insurance',
            'priority' => 'high',
        ];

        // Verify the data exists but exclude the template_stored_file_id that should be filtered out
        $this->assertEquals('Test instructions', $result['additional_instructions']);
        $this->assertEquals(456, $result['demand_id']);
        $this->assertEquals('Test Demand', $result['title']);
        $this->assertEquals('insurance', $result['category']);
        $this->assertEquals('high', $result['priority']);
        $this->assertArrayNotHasKey('template_stored_file_id', $result);
    }
}