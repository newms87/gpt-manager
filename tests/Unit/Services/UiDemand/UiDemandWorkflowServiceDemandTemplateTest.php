<?php

namespace Tests\Unit\Services\UiDemand;

use App\Models\DemandTemplate;
use App\Models\UiDemand;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Repositories\WorkflowInputRepository;
use App\Services\UiDemand\UiDemandWorkflowService;
use App\Services\Workflow\WorkflowRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWorkflowServiceDemandTemplateTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected UiDemandWorkflowService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(UiDemandWorkflowService::class);
    }

    public function test_writeDemand_withTemplateId_createsWorkflowInputWithTemplateData(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'status' => UiDemand::STATUS_COMPLETED,
            'title' => 'Test Demand',
            'description' => 'Test Description',
        ]);

        // Mock workflow definition
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'write_demand',
        ]);

        // Mock WorkflowRunnerService
        $this->mock(WorkflowRunnerService::class)
            ->shouldReceive('start')
            ->once()
            ->andReturn((object) ['id' => 123]);

        // When
        $this->service->writeDemand($uiDemand, $template->stored_file_id, 'Additional instructions');

        // Then - Verify workflow input was created with template data
        $this->assertDatabaseHas('workflow_inputs', [
            'name' => 'Write Demand',
        ]);

        $workflowInput = \App\Models\Workflow\WorkflowInput::where('name', 'Write Demand')->first();
        $artifact = $workflowInput->toArtifact();
        
        $this->assertEquals($template->stored_file_id, $artifact->json_content['template_stored_file_id']);
        $this->assertEquals('Additional instructions', $artifact->json_content['additional_instructions']);
        $this->assertEquals($uiDemand->id, $artifact->json_content['demand_id']);
        $this->assertEquals('Test Demand', $artifact->json_content['title']);
        $this->assertEquals('Test Description', $artifact->json_content['description']);
    }

    public function test_writeDemand_withoutTemplateId_createsWorkflowInputWithoutTemplateData(): void
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
            'title' => 'Test Demand',
            'description' => 'Test Description',
        ]);

        // Mock workflow definition
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'write_demand',
        ]);

        // Mock WorkflowRunnerService
        $this->mock(WorkflowRunnerService::class)
            ->shouldReceive('start')
            ->once()
            ->andReturn((object) ['id' => 123]);

        // When
        $this->service->writeDemand($uiDemand);

        // Then - Verify workflow input was created without template data
        $this->assertDatabaseHas('workflow_inputs', [
            'name' => 'Write Demand',
        ]);

        $workflowInput = \App\Models\Workflow\WorkflowInput::where('name', 'Write Demand')->first();
        $artifact = $workflowInput->toArtifact();
        
        $this->assertArrayNotHasKey('template_stored_file_id', $artifact->json_content);
        $this->assertArrayNotHasKey('additional_instructions', $artifact->json_content);
        $this->assertEquals($uiDemand->id, $artifact->json_content['demand_id']);
        $this->assertEquals('Test Demand', $artifact->json_content['title']);
        $this->assertEquals('Test Description', $artifact->json_content['description']);
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
            'title' => 'Test Demand',
        ]);

        // Mock workflow definition
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'write_demand',
        ]);

        // Mock WorkflowRunnerService
        $this->mock(WorkflowRunnerService::class)
            ->shouldReceive('start')
            ->once()
            ->andReturn((object) ['id' => 123]);

        // When
        $this->service->writeDemand($uiDemand, null, 'Custom instructions only');

        // Then - Verify workflow input includes instructions but no template
        $workflowInput = \App\Models\Workflow\WorkflowInput::where('name', 'Write Demand')->first();
        $artifact = $workflowInput->toArtifact();
        
        $this->assertArrayNotHasKey('template_stored_file_id', $artifact->json_content);
        $this->assertEquals('Custom instructions only', $artifact->json_content['additional_instructions']);
    }

    public function test_writeDemand_withInvalidDemandStatus_throwsValidationError(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'status' => UiDemand::STATUS_DRAFT, // Invalid status for write demand
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot write demand');

        // When
        $this->service->writeDemand($uiDemand);
    }

    public function test_createWorkflowInputFromTeamObject_withTemplateAndInstructions_includesBothInContent(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'title' => 'Test Demand',
            'description' => 'Test Description',
        ]);

        // When - Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createWorkflowInputFromTeamObject');
        $method->setAccessible(true);

        $workflowInput = $method->invoke(
            $this->service,
            $uiDemand,
            $teamObject,
            'Write Demand',
            $template->stored_file_id,
            'Test additional instructions'
        );

        // Then
        $this->assertInstanceOf(\App\Models\Workflow\WorkflowInput::class, $workflowInput);
        
        $artifact = $workflowInput->toArtifact();
        $this->assertEquals($uiDemand->id, $artifact->json_content['demand_id']);
        $this->assertEquals('Test Demand', $artifact->json_content['title']);
        $this->assertEquals('Test Description', $artifact->json_content['description']);
        $this->assertEquals($template->stored_file_id, $artifact->json_content['template_stored_file_id']);
        $this->assertEquals('Test additional instructions', $artifact->json_content['additional_instructions']);
    }

    public function test_createWorkflowInputFromTeamObject_withoutOptionalParams_excludesThemFromContent(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'insurance_demand',
        ]);

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
            'title' => 'Test Demand',
            'description' => 'Test Description',
        ]);

        // When - Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createWorkflowInputFromTeamObject');
        $method->setAccessible(true);

        $workflowInput = $method->invoke(
            $this->service,
            $uiDemand,
            $teamObject,
            'Write Demand'
        );

        // Then
        $artifact = $workflowInput->toArtifact();
        $this->assertEquals($uiDemand->id, $artifact->json_content['demand_id']);
        $this->assertEquals('Test Demand', $artifact->json_content['title']);
        $this->assertEquals('Test Description', $artifact->json_content['description']);
        $this->assertArrayNotHasKey('template_stored_file_id', $artifact->json_content);
        $this->assertArrayNotHasKey('additional_instructions', $artifact->json_content);
    }
}