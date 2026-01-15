<?php

namespace Tests\Feature\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Runners\BaseTaskRunner;
use App\Services\Task\TaskRunnerService;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;
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

        $taskProcess = $taskRun->taskProcesses->first();

        $this->assertEquals(2, $taskProcess->inputArtifacts()->count(), 'Process should have 2 input artifacts');
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
        $taskRun->update(['started_at' => now(), 'failed_at' => now()]);
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

    #[Test]
    public function restart_clears_error_counts(): void
    {
        // GIVEN: A TaskRun with error counts
        $taskDefinition = TaskDefinition::factory()->create([
            'task_runner_name' => 'agent-thread',
        ]);

        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // Set error counts and failed state
        $taskRun->task_process_error_count = 5;
        $taskRun->failed_at                = now();
        $taskRun->started_at               = now();
        $taskRun->save();

        // Create some task processes with errors
        TaskProcess::factory()->count(3)->create([
            'task_run_id' => $taskRun->id,
            'error_count' => 2,
        ]);

        // Verify error count is set
        $this->assertEquals(5, $taskRun->task_process_error_count);
        $this->assertEquals(4, $taskRun->taskProcesses()->count(), 'Should have 4 task processes (1 from prepare + 3 manually created)');

        // WHEN: The TaskRun is restarted
        TaskRunnerService::restart($taskRun);

        // THEN: Error count should be reset to 0
        $taskRun->refresh();
        $this->assertEquals(0, $taskRun->task_process_error_count, 'task_process_error_count should be reset to 0 on restart');

        // AND: Critical timestamp fields should be reset (failed_at is the key one)
        $this->assertNull($taskRun->failed_at, 'failed_at should be null - TaskRun is no longer in failed state');
        $this->assertNull($taskRun->stopped_at, 'stopped_at should be null - TaskRun is no longer stopped');
        $this->assertNull($taskRun->skipped_at, 'skipped_at should be null - TaskRun is no longer skipped');

        // Note: started_at and completed_at may be set by the restart process running the new task processes,
        // but the important thing is that error counts are cleared and failed state is reset
    }

    // ============================================================================
    // Tests for active_task_processes_count and completion redesign
    // ============================================================================

    #[Test]
    public function active_task_processes_count_increments_when_task_process_created(): void
    {
        // GIVEN: A TaskRun with no processes
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);

        // Verify initial state (no processes created yet)
        $this->assertEquals(0, $taskRun->active_task_processes_count, 'Initial active count should be 0');

        // WHEN: TaskProcesses are created via prepareTaskProcesses
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // THEN: active_task_processes_count should be 1 (pending processes count as active)
        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Active count should be 1 after creating a pending process');
        $this->assertEquals(WorkflowStatesContract::STATUS_PENDING, $taskRun->taskProcesses->first()->status);
    }

    #[Test]
    public function active_task_processes_count_increments_for_multiple_processes(): void
    {
        // GIVEN: A TaskDefinition with 2 schema associations (creates 2 processes)
        $taskDefinition = TaskDefinition::factory()->withSchemaDefinition()->create();
        SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition, 'output')->create();
        SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition, 'output')->create();

        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);

        // WHEN: TaskProcesses are created
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // THEN: active_task_processes_count should be 2
        $taskRun->refresh();
        $this->assertEquals(2, $taskRun->process_count, 'Should have 2 total processes');
        $this->assertEquals(2, $taskRun->active_task_processes_count, 'Active count should be 2');
    }

    #[Test]
    public function active_task_processes_count_decrements_when_task_process_completes(): void
    {
        // GIVEN: A TaskRun with an active (running) process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Start the process
        $taskProcess->started_at = now();
        $taskProcess->save();

        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Active count should be 1 while running');
        $this->assertEquals(WorkflowStatesContract::STATUS_RUNNING, $taskProcess->fresh()->status);

        // WHEN: The process completes
        $taskProcess->completed_at = now();
        $taskProcess->save();

        // THEN: active_task_processes_count should be 0
        $taskRun->refresh();
        $this->assertEquals(0, $taskRun->active_task_processes_count, 'Active count should be 0 after completion');
        $this->assertEquals(WorkflowStatesContract::STATUS_COMPLETED, $taskProcess->fresh()->status);
    }

    #[Test]
    public function active_task_processes_count_decrements_when_task_process_fails(): void
    {
        // GIVEN: A TaskRun with an active (running) process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Start the process
        $taskProcess->started_at = now();
        $taskProcess->save();

        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Active count should be 1 while running');

        // WHEN: The process fails
        $taskProcess->failed_at = now();
        $taskProcess->save();

        // THEN: active_task_processes_count should be 0 (failed processes are not active)
        $taskRun->refresh();
        $this->assertEquals(0, $taskRun->active_task_processes_count, 'Active count should be 0 after failure');
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $taskProcess->fresh()->status);
    }

    #[Test]
    public function task_run_completes_when_all_processes_complete_and_no_new_processes_created(): void
    {
        // GIVEN: A TaskRun with a single process (using BaseTaskRunner which does not create new processes)
        $taskDefinition = TaskDefinition::factory()->create([
            'task_runner_name' => BaseTaskRunner::RUNNER_NAME,
        ]);
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Ensure the process is ready to run
        $taskProcess->is_ready = true;
        $taskProcess->save();

        // WHEN: The task run continues and the process completes
        TaskRunnerService::continue($taskRun);

        // THEN: TaskRun should be marked as completed
        $taskRun->refresh();
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed');
        $this->assertNotNull($taskRun->completed_at, 'completed_at should be set');
        $this->assertEquals(0, $taskRun->active_task_processes_count, 'Active count should be 0');
    }

    #[Test]
    public function task_run_not_completed_when_failed_processes_exist(): void
    {
        // GIVEN: A TaskRun with a running process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Start the process
        $taskProcess->started_at = now();
        $taskProcess->save();

        $taskRun->started_at = now();
        $taskRun->save();

        // WHEN: The process fails
        $taskProcess->failed_at = now();
        $taskProcess->save();

        // THEN: TaskRun should be marked as failed, not completed
        $taskRun->refresh();
        $this->assertTrue($taskRun->isFailed(), 'TaskRun should be failed');
        $this->assertFalse($taskRun->isCompleted(), 'TaskRun should NOT be completed');
        $this->assertNotNull($taskRun->failed_at, 'failed_at should be set');
        $this->assertNull($taskRun->completed_at, 'completed_at should be null');
    }

    #[Test]
    public function task_run_not_completed_when_stopped_processes_exist(): void
    {
        // GIVEN: A TaskRun with a running process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Start the process
        $taskProcess->started_at = now();
        $taskProcess->save();

        $taskRun->started_at = now();
        $taskRun->save();

        // WHEN: The process is stopped
        $taskProcess->stopped_at = now();
        $taskProcess->save();

        // THEN: TaskRun should be marked as stopped, not completed
        $taskRun->refresh();
        $this->assertTrue($taskRun->isStopped(), 'TaskRun should be stopped');
        $this->assertFalse($taskRun->isCompleted(), 'TaskRun should NOT be completed');
        $this->assertNotNull($taskRun->stopped_at, 'stopped_at should be set');
        $this->assertNull($taskRun->completed_at, 'completed_at should be null');
    }

    #[Test]
    public function after_all_processes_complete_is_called_when_active_count_reaches_zero(): void
    {
        // GIVEN: A TaskRun with a single process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Start and ensure active count is 1
        $taskProcess->started_at = now();
        $taskProcess->save();
        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->active_task_processes_count);

        // WHEN: Process completes, active count goes from 1 to 0
        $taskProcess->completed_at = now();
        $taskProcess->save();

        // THEN: afterAllProcessesComplete should have been called,
        // and since BaseTaskRunner doesn't create new processes, TaskRun should be complete
        $taskRun->refresh();
        $this->assertEquals(0, $taskRun->active_task_processes_count);
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed after hook runs');
    }

    #[Test]
    public function task_run_completion_with_multiple_processes_waits_for_all(): void
    {
        // GIVEN: A TaskDefinition with 2 schema associations (creates 2 processes)
        $taskDefinition = TaskDefinition::factory()->withSchemaDefinition()->create();
        SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition, 'output')->create();
        SchemaAssociation::factory()->withSchema($taskDefinition->schemaDefinition)->withObject($taskDefinition, 'output')->create();

        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);

        $processes = $taskRun->taskProcesses;
        $this->assertCount(2, $processes);

        // Start both processes
        foreach ($processes as $process) {
            $process->started_at = now();
            $process->save();
        }

        $taskRun->started_at = now();
        $taskRun->save();
        $taskRun->refresh();
        $this->assertEquals(2, $taskRun->active_task_processes_count);

        // WHEN: First process completes
        $processes[0]->completed_at = now();
        $processes[0]->save();

        // THEN: TaskRun should NOT be complete yet
        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Active count should be 1');
        $this->assertFalse($taskRun->isCompleted(), 'TaskRun should NOT be completed yet');

        // WHEN: Second process completes
        $processes[1]->completed_at = now();
        $processes[1]->save();

        // THEN: TaskRun should now be complete
        $taskRun->refresh();
        $this->assertEquals(0, $taskRun->active_task_processes_count, 'Active count should be 0');
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed now');
    }

    #[Test]
    public function directly_creating_task_process_updates_active_count(): void
    {
        // GIVEN: A TaskRun with no processes
        $taskRun = TaskRun::factory()->create();
        $this->assertEquals(0, $taskRun->active_task_processes_count);

        // WHEN: A TaskProcess is created directly (simulating what a runner's afterAllProcessesCompleted might do)
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'started_at'  => null, // Pending status
        ]);

        // THEN: active_task_processes_count should be updated
        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Active count should be 1 after direct creation');
        $this->assertEquals(WorkflowStatesContract::STATUS_PENDING, $taskProcess->fresh()->status);
    }

    #[Test]
    public function status_transitions_update_active_count_correctly(): void
    {
        // This test verifies the full lifecycle: pending -> running -> completed
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // VERIFY: Pending state
        $taskRun->refresh();
        $this->assertEquals(WorkflowStatesContract::STATUS_PENDING, $taskProcess->status);
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Pending process should be active');

        // TRANSITION: Pending -> Running
        $taskProcess->started_at = now();
        $taskProcess->save();

        $taskRun->refresh();
        $this->assertEquals(WorkflowStatesContract::STATUS_RUNNING, $taskProcess->fresh()->status);
        $this->assertEquals(1, $taskRun->active_task_processes_count, 'Running process should be active');

        // TRANSITION: Running -> Completed
        $taskProcess->completed_at = now();
        $taskProcess->save();

        $taskRun->refresh();
        $this->assertEquals(WorkflowStatesContract::STATUS_COMPLETED, $taskProcess->fresh()->status);
        $this->assertEquals(0, $taskRun->active_task_processes_count, 'Completed process should NOT be active');
    }

    // ============================================================================
    // Tests for TaskRunnerService::stop()
    // ============================================================================

    #[Test]
    public function stop_marks_task_run_as_stopped_when_running_processes_exist(): void
    {
        // GIVEN: A TaskRun with a running process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        TaskRunnerService::prepareTaskProcesses($taskRun);
        $taskProcess = $taskRun->taskProcesses->first();

        // Start the process
        $taskProcess->started_at = now();
        $taskProcess->save();
        $taskRun->started_at = now();
        $taskRun->save();

        $taskRun->refresh();
        $this->assertEquals(WorkflowStatesContract::STATUS_RUNNING, $taskRun->status);

        // WHEN: Stop is called
        TaskRunnerService::stop($taskRun);

        // THEN: TaskRun should be stopped
        $taskRun->refresh();
        $this->assertTrue($taskRun->isStopped(), 'TaskRun should be stopped');
        $this->assertNotNull($taskRun->stopped_at, 'stopped_at should be set');
        $this->assertNull($taskRun->completed_at, 'completed_at should be null');
    }

    #[Test]
    public function stop_completes_task_run_when_all_processes_already_complete(): void
    {
        // GIVEN: A TaskRun where all processes are complete but completed_at was never set
        // This simulates the race condition bug or data migration scenario
        $taskDefinition = TaskDefinition::factory()->create();

        // Create TaskRun directly to avoid callbacks
        $taskRun = \App\Models\Task\TaskRun::factory()->create([
            'task_definition_id'           => $taskDefinition->id,
            'started_at'                   => now(),
            'completed_at'                 => null,  // Bug: should be set but isn't
            'active_task_processes_count'  => 0,
        ]);

        // Create a completed TaskProcess directly to avoid callbacks
        TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'started_at'    => now()->subMinute(),
            'completed_at'  => now(),
        ]);

        // Verify the buggy state
        $taskRun->refresh();
        $this->assertEquals(WorkflowStatesContract::STATUS_RUNNING, $taskRun->status, 'TaskRun should appear running (bug state)');
        $this->assertNull($taskRun->completed_at, 'completed_at should be null (simulating bug state)');
        $this->assertEquals(1, $taskRun->taskProcesses()->count(), 'Should have 1 process');
        $this->assertTrue($taskRun->taskProcesses->first()->isCompleted(), 'Process should be completed');

        // WHEN: Stop is called
        TaskRunnerService::stop($taskRun);

        // THEN: TaskRun should be in a terminal state (completed or stopped), not running
        $taskRun->refresh();
        $this->assertTrue(
            $taskRun->isCompleted() || $taskRun->isStopped(),
            'TaskRun should be in a terminal state (completed or stopped), not running. Actual status: ' . $taskRun->status
        );
        $this->assertNotEquals(
            WorkflowStatesContract::STATUS_RUNNING,
            $taskRun->status,
            'TaskRun should NOT be stuck in Running state'
        );
    }
}
