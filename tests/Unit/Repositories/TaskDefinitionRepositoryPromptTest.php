<?php

namespace Tests\Unit\Repositories;

use App\Models\Task\TaskDefinition;
use App\Repositories\TaskDefinitionRepository;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskDefinitionRepositoryPromptTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private TaskDefinitionRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->repository = new TaskDefinitionRepository();
    }

    public function test_createTaskDefinition_withPrompt_createsTaskDefinitionWithPromptField(): void
    {
        // Given
        $data = [
            'name' => 'Test Task Definition',
            'description' => 'Test description',
            'prompt' => 'This is a test prompt for the task definition',
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Test Task Definition', $result->name);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals('This is a test prompt for the task definition', $result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'name' => 'Test Task Definition',
            'prompt' => 'This is a test prompt for the task definition',
        ]);
    }

    public function test_createTaskDefinition_withNullPrompt_createsTaskDefinitionWithNullPrompt(): void
    {
        // Given
        $data = [
            'name' => 'Task Without Prompt',
            'description' => 'Test description',
            'prompt' => null,
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Task Without Prompt', $result->name);
        $this->assertNull($result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'name' => 'Task Without Prompt',
            'prompt' => null,
        ]);
    }

    public function test_createTaskDefinition_withEmptyPrompt_createsTaskDefinitionWithEmptyPrompt(): void
    {
        // Given
        $data = [
            'name' => 'Task With Empty Prompt',
            'description' => 'Test description',
            'prompt' => '',
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Task With Empty Prompt', $result->name);
        $this->assertEquals('', $result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'name' => 'Task With Empty Prompt',
            'prompt' => '',
        ]);
    }

    public function test_createTaskDefinition_withLongPrompt_createsTaskDefinitionWithLongPrompt(): void
    {
        // Given
        $longPrompt = str_repeat('This is a very long prompt text. ', 100);
        $data = [
            'name' => 'Task With Long Prompt',
            'description' => 'Test description',
            'prompt' => $longPrompt,
        ];

        // When
        $result = $this->repository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Task With Long Prompt', $result->name);
        $this->assertEquals($longPrompt, $result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'name' => 'Task With Long Prompt',
            'prompt' => $longPrompt,
        ]);
    }

    public function test_updateTaskDefinition_withNewPrompt_updatesPromptField(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Original Task',
            'prompt' => 'Original prompt',
        ]);

        $updateData = [
            'name' => 'Updated Task',
            'prompt' => 'Updated prompt content for the task',
        ];

        // When
        $result = $this->repository->applyAction('update', $taskDefinition, $updateData);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Updated Task', $result->name);
        $this->assertEquals('Updated prompt content for the task', $result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $taskDefinition->id,
            'name' => 'Updated Task',
            'prompt' => 'Updated prompt content for the task',
        ]);
    }

    public function test_updateTaskDefinition_withEmptyPrompt_updatesPromptToEmpty(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Task With Prompt',
            'prompt' => 'Original prompt content',
        ]);

        $updateData = [
            'prompt' => '',
        ];

        // When
        $result = $this->repository->applyAction('update', $taskDefinition, $updateData);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('', $result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $taskDefinition->id,
            'prompt' => '',
        ]);
    }

    public function test_updateTaskDefinition_withNullPrompt_updatesPromptToNull(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Task With Prompt',
            'prompt' => 'Original prompt content',
        ]);

        $updateData = [
            'prompt' => null,
        ];

        // When
        $result = $this->repository->applyAction('update', $taskDefinition, $updateData);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertNull($result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $taskDefinition->id,
            'prompt' => null,
        ]);
    }

    public function test_updateTaskDefinition_withoutPromptInData_preservesExistingPrompt(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Task With Prompt',
            'prompt' => 'Original prompt content',
        ]);

        $updateData = [
            'name' => 'Updated Task Name',
            'description' => 'Updated description',
        ];

        // When
        $result = $this->repository->applyAction('update', $taskDefinition, $updateData);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertEquals('Updated Task Name', $result->name);
        $this->assertEquals('Original prompt content', $result->prompt);
        
        // Verify database persistence
        $this->assertDatabaseHas('task_definitions', [
            'id' => $taskDefinition->id,
            'name' => 'Updated Task Name',
            'prompt' => 'Original prompt content',
        ]);
    }

    public function test_copyTaskDefinition_preservesPromptField(): void
    {
        // Given
        $originalTaskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Original Task',
            'description' => 'Original description',
            'prompt' => 'Original prompt for copying test',
            'task_runner_name' => AgentThreadTaskRunner::RUNNER_NAME,
            'timeout_after_seconds' => 600,
        ]);

        // When
        $result = $this->repository->applyAction('copy', $originalTaskDefinition);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertNotEquals($originalTaskDefinition->id, $result->id);
        $this->assertNotEquals($originalTaskDefinition->name, $result->name); // Name gets modified
        $this->assertEquals($originalTaskDefinition->description, $result->description);
        $this->assertEquals('Original prompt for copying test', $result->prompt);
        $this->assertEquals($originalTaskDefinition->task_runner_name, $result->task_runner_name);
        $this->assertEquals($originalTaskDefinition->timeout_after_seconds, $result->timeout_after_seconds);
        
        // Verify database persistence of copied task
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'prompt' => 'Original prompt for copying test',
            'description' => $originalTaskDefinition->description,
            'team_id' => team()->id,
        ]);
        
        // Verify original task remains unchanged
        $this->assertDatabaseHas('task_definitions', [
            'id' => $originalTaskDefinition->id,
            'name' => 'Original Task',
            'prompt' => 'Original prompt for copying test',
        ]);
    }

    public function test_copyTaskDefinition_withNullPrompt_preservesNullPrompt(): void
    {
        // Given
        $originalTaskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Task Without Prompt',
            'description' => 'Task description',
            'prompt' => null,
        ]);

        // When
        $result = $this->repository->applyAction('copy', $originalTaskDefinition);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertNotEquals($originalTaskDefinition->id, $result->id);
        $this->assertNull($result->prompt);
        
        // Verify database persistence of copied task
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'prompt' => null,
            'team_id' => team()->id,
        ]);
    }

    public function test_copyTaskDefinition_withEmptyPrompt_preservesEmptyPrompt(): void
    {
        // Given
        $originalTaskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Task With Empty Prompt',
            'description' => 'Task description',
            'prompt' => '',
        ]);

        // When
        $result = $this->repository->applyAction('copy', $originalTaskDefinition);

        // Then
        $this->assertInstanceOf(TaskDefinition::class, $result);
        $this->assertNotEquals($originalTaskDefinition->id, $result->id);
        $this->assertEquals('', $result->prompt);
        
        // Verify database persistence of copied task
        $this->assertDatabaseHas('task_definitions', [
            'id' => $result->id,
            'prompt' => '',
            'team_id' => team()->id,
        ]);
    }

    public function test_exportToJson_includesPromptField(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Export Test Task',
            'description' => 'Task for export testing',
            'prompt' => 'Prompt content for export test',
            'task_runner_name' => AgentThreadTaskRunner::RUNNER_NAME,
            'timeout_after_seconds' => 300,
        ]);

        // Load the task definition with all necessary relationships for export
        $taskDefinition->load([
            'taskArtifactFiltersAsTarget',
            'schemaAssociations',
            'taskDefinitionDirectives',
            'schemaDefinition',
            'agent'
        ]);

        // Mock the WorkflowExportService
        $workflowExportService = $this->mock(\App\Services\Workflow\WorkflowExportService::class);
        
        // Set up expectations for the mock
        $workflowExportService->shouldReceive('registerRelatedModels')->times(3);
        $workflowExportService->shouldReceive('registerRelatedModel')->twice()->andReturn(null);
        $workflowExportService->shouldReceive('register')
            ->once()
            ->with($taskDefinition, \Mockery::on(function ($data) {
                return isset($data['prompt']) && 
                       $data['prompt'] === 'Prompt content for export test' &&
                       isset($data['name']) && 
                       $data['name'] === 'Export Test Task' &&
                       isset($data['description']) &&
                       $data['description'] === 'Task for export testing';
            }))
            ->andReturn(1);

        // When
        $result = $taskDefinition->exportToJson($workflowExportService);

        // Then
        $this->assertEquals(1, $result);
    }

    public function test_exportToJson_withNullPrompt_includesNullPromptInExport(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Export Test Task No Prompt',
            'description' => 'Task for export testing without prompt',
            'prompt' => null,
        ]);

        // Load the task definition with all necessary relationships for export
        $taskDefinition->load([
            'taskArtifactFiltersAsTarget',
            'schemaAssociations', 
            'taskDefinitionDirectives',
            'schemaDefinition',
            'agent'
        ]);

        // Mock the WorkflowExportService
        $workflowExportService = $this->mock(\App\Services\Workflow\WorkflowExportService::class);
        
        // Set up expectations for the mock
        $workflowExportService->shouldReceive('registerRelatedModels')->times(3);
        $workflowExportService->shouldReceive('registerRelatedModel')->twice()->andReturn(null);
        $workflowExportService->shouldReceive('register')
            ->once()
            ->with($taskDefinition, \Mockery::on(function ($data) {
                return array_key_exists('prompt', $data) && 
                       $data['prompt'] === null &&
                       isset($data['name']) && 
                       $data['name'] === 'Export Test Task No Prompt';
            }))
            ->andReturn(1);

        // When
        $result = $taskDefinition->exportToJson($workflowExportService);

        // Then
        $this->assertEquals(1, $result);
    }

    public function test_exportToJson_withEmptyPrompt_includesEmptyPromptInExport(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => team()->id,
            'name' => 'Export Test Task Empty Prompt',
            'description' => 'Task for export testing with empty prompt',
            'prompt' => '',
        ]);

        // Load the task definition with all necessary relationships for export
        $taskDefinition->load([
            'taskArtifactFiltersAsTarget',
            'schemaAssociations',
            'taskDefinitionDirectives', 
            'schemaDefinition',
            'agent'
        ]);

        // Mock the WorkflowExportService
        $workflowExportService = $this->mock(\App\Services\Workflow\WorkflowExportService::class);
        
        // Set up expectations for the mock
        $workflowExportService->shouldReceive('registerRelatedModels')->times(3);
        $workflowExportService->shouldReceive('registerRelatedModel')->twice()->andReturn(null);
        $workflowExportService->shouldReceive('register')
            ->once()
            ->with($taskDefinition, \Mockery::on(function ($data) {
                return isset($data['prompt']) && 
                       $data['prompt'] === '' &&
                       isset($data['name']) && 
                       $data['name'] === 'Export Test Task Empty Prompt';
            }))
            ->andReturn(1);

        // When
        $result = $taskDefinition->exportToJson($workflowExportService);

        // Then
        $this->assertEquals(1, $result);
    }
}