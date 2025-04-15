<?php

namespace Tests\Feature\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Services\Task\TaskRunnerService;
use Illuminate\Database\Eloquent\Builder;
use Tests\AuthenticatedTestCase;

class TaskRunnerServiceTest extends AuthenticatedTestCase
{
    public function test_prepareTaskRun_createsTaskRunWithSingleProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(1, $taskRun->taskProcesses, 'TaskRun should have a single TaskProcess');
        $this->assertEquals(1, $taskRun->process_count, 'TaskRun should have a process count of 1');
    }

    public function test_prepareTaskRun_createsTaskRunWithOneProcessForEachSchemaAssociation(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->withSchemaDefinition()->create();
        $schemaB        = SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition, 'output')->create();
        $schemaA        = SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition, 'output')->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(2, $taskRun->taskProcesses, 'TaskRun should have 2 processes: 1 for each schema association');
        $this->assertEquals(2, $taskRun->process_count, 'The process_count should reflect the 2 processes created');

        $taskProcessSchemaAExists = $taskRun->taskProcesses()->whereHas('outputSchemaAssociation', fn(Builder $builder) => $builder->where('schema_fragment_id', $schemaA->schema_fragment_id))->exists();
        $taskProcessSchemaBExists = $taskRun->taskProcesses()->whereHas('outputSchemaAssociation', fn(Builder $builder) => $builder->where('schema_fragment_id', $schemaB->schema_fragment_id))->exists();

        $this->assertTrue($taskProcessSchemaAExists, 'A Task Process should have been found with schema association A');
        $this->assertTrue($taskProcessSchemaBExists, 'A Task Process should have been found with schema association B');
    }

    public function test_prepareTaskRun_withArtifacts_createsOneProcessForAllArtifacts(): void
    {
        // Given
        $agentA         = Agent::factory()->create();
        $taskDefinition = TaskDefinition::factory()->create();
        $artifacts      = Artifact::factory()->count(2)->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskRun->addInputArtifacts($artifacts);
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(1, $taskRun->taskProcesses, 'TaskRun should have a single process for all artifacts');

        $process = $taskRun->taskProcesses->first();

        $this->assertEquals(2, $process->inputArtifacts()->count(), 'Process should have 2 input artifacts');
    }

    public function test_prepareTaskRun_withArtifactsAndMultipleSchemaAssociations_createsAProcessWithAllArtifactsForEachSchemaAssociation(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->withSchemaDefinition()->create();
        $schemaA        = SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition)->create();
        $schemaB        = SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition)->create();
        $artifacts      = Artifact::factory()->count(2)->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskRun->addInputArtifacts($artifacts);
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(2, $taskRun->taskProcesses, 'TaskRun should have 2 processes: 1 for each agent');

        $processes = $taskRun->taskProcesses;
        $processA  = $processes->filter(fn($process) => $process->outputSchemaAssociation->schema_fragment_id === $schemaA->schema_fragment_id)->first();
        $processB  = $processes->filter(fn($process) => $process->outputSchemaAssociation->schema_fragment_id === $schemaB->schema_fragment_id)->first();

        $this->assertNotEquals($processA->id, $processB->id, 'Processes should be different');
        $this->assertEquals(2, $processA->inputArtifacts()->count(), 'Process A should have 2 input artifacts');
        $this->assertEquals(2, $processB->inputArtifacts()->count(), 'Process B should have 2 input artifacts');
    }

    public function test_continue_whenTaskRunIsPending_dispatchesAJobForTaskProcessToCompletion(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then After
        $taskRun->refresh();
        $taskProcess->refresh();
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed');
        $this->assertFalse($taskRun->isFailed(), 'TaskRun should not fail');
        $this->assertFalse($taskRun->isStopped(), 'TaskRun should not be stopped');

        $this->assertTrue($taskProcess->isCompleted(), 'TaskProcess should be completed');
        $this->assertFalse($taskProcess->isFailed(), 'TaskProcess should not fail');
        $this->assertFalse($taskProcess->isStopped(), 'TaskProcess should not be stopped');

        $this->assertEquals(1, $taskProcess->jobDispatches()->count(), 'TaskProcess should have a single JobDispatch');
    }

    public function test_continue_whenTaskRunIsStopped_doesNotDispatchAJobForTaskProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskRun->update(['stopped_at' => now()]);
        $taskProcess = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then
        $this->assertEquals(0, $taskProcess->jobDispatches()->count(), 'TaskProcess should not have been dispatched');
    }

    public function test_continue_whenTaskRunIsFailed_doesNotDispatchAJobForTaskProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskRun->update(['failed_at' => now()]);
        $taskProcess = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then
        $this->assertEquals(0, $taskProcess->jobDispatches()->count(), 'TaskProcess should not have been dispatched');
    }

    public function test_continue_whenRunningAgentThread_completedTaskHasOutputArtifact(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then After
        $taskRun->refresh();
        $taskProcess->refresh();
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed');
        $this->assertEquals(0, $taskProcess->outputArtifacts()->count(), 'TaskProcess should not have any output artifacts');
    }
}
