<?php

namespace Tests\Feature\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Runners\BaseTaskRunner;
use App\Services\Task\TaskRunnerService;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Exceptions\ValidationError;
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
        // GIVEN: A TaskRun with error counts that is in FAILED state (not running)
        $taskDefinition = TaskDefinition::factory()->create([
            'task_runner_name' => 'agent-thread',
        ]);

        // Create a failed TaskRun directly to avoid status recalculation issues
        $taskRun = TaskRun::factory()->create([
            'task_definition_id'       => $taskDefinition->id,
            'task_process_error_count' => 5,
            'failed_at'                => now(),
            'started_at'               => now(),
        ]);

        // Create some completed task processes with errors (not active/pending)
        TaskProcess::factory()->count(3)->create([
            'task_run_id'  => $taskRun->id,
            'error_count'  => 2,
            'started_at'   => now(),
            'completed_at' => now(),
            'failed_at'    => now(),
        ]);

        // Verify error count is set and task run is in failed state
        $this->assertEquals(5, $taskRun->task_process_error_count);
        $this->assertEquals(3, $taskRun->taskProcesses()->count());
        $this->assertTrue($taskRun->isFailed(), 'TaskRun should be in failed state');

        // WHEN: The TaskRun is restarted (returns the NEW task run)
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: NEW TaskRun should have error count reset to 0
        $this->assertEquals(0, $newTaskRun->task_process_error_count, 'task_process_error_count should be reset to 0 on new run');

        // AND: NEW TaskRun should have reset timestamp fields
        $this->assertNull($newTaskRun->failed_at, 'failed_at should be null on new run');
        $this->assertNull($newTaskRun->stopped_at, 'stopped_at should be null on new run');
        $this->assertNull($newTaskRun->skipped_at, 'skipped_at should be null on new run');

        // AND: Old TaskRun should be soft-deleted and point to new one
        $taskRun->refresh();
        $this->assertNotNull($taskRun->deleted_at, 'Old TaskRun should be soft-deleted');
        $this->assertEquals($newTaskRun->id, $taskRun->parent_task_run_id, 'Old TaskRun should point to new one');
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

    // ============================================================================
    // Tests for TaskRun Restart Clone Feature
    // ============================================================================

    #[Test]
    public function restart_creates_new_task_run_and_soft_deletes_old(): void
    {
        // GIVEN: A completed task run (created directly with completed state)
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'restart_count'      => 0,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        $oldTaskRunId = $taskRun->id;
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed before restart');

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: A new TaskRun is created
        $this->assertNotEquals($oldTaskRunId, $newTaskRun->id, 'New TaskRun should have different ID');
        $this->assertInstanceOf(TaskRun::class, $newTaskRun);

        // AND: Old TaskRun is soft-deleted
        $taskRun->refresh();
        $this->assertNotNull($taskRun->deleted_at, 'Old TaskRun should be soft-deleted');

        // AND: Old TaskRun points to new one via parent_task_run_id
        $this->assertEquals($newTaskRun->id, $taskRun->parent_task_run_id, 'Old TaskRun should point to new one');

        // AND: New TaskRun has incremented restart_count
        $this->assertEquals(1, $newTaskRun->restart_count, 'New TaskRun should have restart_count of 1');
    }

    #[Test]
    public function restart_preserves_task_relationships(): void
    {
        // GIVEN: A completed task run
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: New TaskRun has same task_definition_id
        $this->assertEquals(
            $taskRun->task_definition_id,
            $newTaskRun->task_definition_id,
            'New TaskRun should have same task_definition_id'
        );
    }

    #[Test]
    public function restart_preserves_workflow_relationships(): void
    {
        // GIVEN: A task run that is part of a workflow (with complete relationships)
        $workflowRun  = WorkflowRun::factory()->create();
        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowRun->workflow_definition_id,
        ]);

        // Use the workflow node's task definition to ensure proper relationship
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $workflowNode->task_definition_id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $workflowNode->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: New TaskRun has same workflow_run_id and workflow_node_id
        $this->assertEquals(
            $taskRun->workflow_run_id,
            $newTaskRun->workflow_run_id,
            'New TaskRun should have same workflow_run_id'
        );
        $this->assertEquals(
            $taskRun->workflow_node_id,
            $newTaskRun->workflow_node_id,
            'New TaskRun should have same workflow_node_id'
        );
    }

    #[Test]
    public function restart_preserves_task_input_id(): void
    {
        // GIVEN: A task run with a task_input_id
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_input_id'      => 123,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: New TaskRun has same task_input_id
        $this->assertEquals(
            $taskRun->task_input_id,
            $newTaskRun->task_input_id,
            'New TaskRun should have same task_input_id'
        );
    }

    #[Test]
    public function restart_multiple_times_maintains_flat_chain(): void
    {
        // GIVEN: A completed task run
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun1       = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'restart_count'      => 0,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // WHEN: First restart
        $taskRun2 = TaskRunnerService::restart($taskRun1);

        // Complete second run for next restart
        $taskRun2->started_at   = now();
        $taskRun2->completed_at = now();
        $taskRun2->save();

        // Second restart
        $taskRun3 = TaskRunnerService::restart($taskRun2);

        // THEN: Both old TaskRuns point to the final active TaskRun (flat chain)
        $taskRun1->refresh();
        $taskRun2->refresh();

        $this->assertEquals(
            $taskRun3->id,
            $taskRun1->parent_task_run_id,
            'First old TaskRun should point to final active TaskRun'
        );
        $this->assertEquals(
            $taskRun3->id,
            $taskRun2->parent_task_run_id,
            'Second old TaskRun should point to final active TaskRun'
        );

        // AND: restart_count increments correctly
        $this->assertEquals(0, $taskRun1->restart_count, 'First TaskRun should have restart_count 0');
        $this->assertEquals(1, $taskRun2->restart_count, 'Second TaskRun should have restart_count 1');
        $this->assertEquals(2, $taskRun3->restart_count, 'Third TaskRun should have restart_count 2');

        // AND: Both old runs are soft-deleted
        $this->assertNotNull($taskRun1->deleted_at, 'First TaskRun should be soft-deleted');
        $this->assertNotNull($taskRun2->deleted_at, 'Second TaskRun should be soft-deleted');
        $this->assertNull($taskRun3->deleted_at, 'Third (active) TaskRun should NOT be soft-deleted');
    }

    #[Test]
    public function restart_leaves_child_processes_on_old_run(): void
    {
        // GIVEN: A task run with multiple task processes
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // Create some task processes on the old run
        $process1 = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);
        $process2 = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);

        $oldProcessCount = $taskRun->taskProcesses()->count();
        $this->assertEquals(2, $oldProcessCount, 'Old TaskRun should have 2 processes');

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: Old TaskRun's processes are untouched
        $taskRun->refresh();
        $this->assertEquals(2, $taskRun->taskProcesses()->count(), 'Old TaskRun should still have its processes');

        // AND: Processes still belong to old run (checking via fresh query)
        $process1->refresh();
        $process2->refresh();
        $this->assertEquals($taskRun->id, $process1->task_run_id, 'Process 1 should still belong to old TaskRun');
        $this->assertEquals($taskRun->id, $process2->task_run_id, 'Process 2 should still belong to old TaskRun');

        // Note: In tests, the PrepareTaskProcessJob runs synchronously and creates new processes
        // The key assertion is that OLD processes remain on the OLD run
    }

    #[Test]
    public function restart_throws_validation_error_when_task_run_is_running(): void
    {
        // GIVEN: A task run that is currently running
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'completed_at'       => null, // Not completed
            'failed_at'          => null, // Not failed
            'stopped_at'         => null, // Not stopped
        ]);

        // Create a running process
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'started_at'   => now(),
            'completed_at' => null,
        ]);

        $taskRun->updateActiveProcessCount()->save();
        $taskRun->refresh();

        $this->assertEquals(WorkflowStatesContract::STATUS_RUNNING, $taskRun->status, 'TaskRun should be running');

        // WHEN/THEN: Restart should throw validation error
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('TaskRun is currently running, cannot restart');

        TaskRunnerService::restart($taskRun);
    }

    #[Test]
    public function restart_copies_input_artifacts_for_standalone_task(): void
    {
        // GIVEN: A standalone task run (no workflow) with input artifacts
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'workflow_run_id'    => null,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // Add input artifacts
        $artifact1 = Artifact::factory()->create();
        $artifact2 = Artifact::factory()->create();
        $taskRun->inputArtifacts()->sync([$artifact1->id, $artifact2->id]);
        $taskRun->updateRelationCounter('inputArtifacts');

        $this->assertEquals(2, $taskRun->inputArtifacts()->count(), 'Old TaskRun should have 2 input artifacts');

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: New TaskRun has the same input artifacts
        $this->assertEquals(2, $newTaskRun->inputArtifacts()->count(), 'New TaskRun should have 2 input artifacts');

        $oldArtifactIds = $taskRun->inputArtifacts()->pluck('artifacts.id')->sort()->values()->toArray();
        $newArtifactIds = $newTaskRun->inputArtifacts()->pluck('artifacts.id')->sort()->values()->toArray();

        $this->assertEquals($oldArtifactIds, $newArtifactIds, 'New TaskRun should have same input artifact IDs');
    }

    #[Test]
    public function restart_syncs_input_artifacts_from_workflow_source_nodes(): void
    {
        // GIVEN: A workflow with source node that has output artifacts
        $workflowRun = WorkflowRun::factory()->create();

        // Create source node and its task run with output artifacts
        $sourceNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowRun->workflow_definition_id,
        ]);
        $sourceTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $sourceNode->task_definition_id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $sourceNode->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // Add output artifacts to source task run
        $artifact1 = Artifact::factory()->create();
        $artifact2 = Artifact::factory()->create();
        $sourceTaskRun->outputArtifacts()->sync([$artifact1->id, $artifact2->id]);

        // Create target node and its task run
        $targetNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowRun->workflow_definition_id,
        ]);

        // Create connection from source to target
        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflowRun->workflow_definition_id,
            'source_node_id'         => $sourceNode->id,
            'target_node_id'         => $targetNode->id,
        ]);

        $targetTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $targetNode->task_definition_id,
            'workflow_run_id'    => $workflowRun->id,
            'workflow_node_id'   => $targetNode->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // Ensure relationships are loaded
        $targetTaskRun->load(['workflowRun', 'workflowNode']);

        // WHEN: The target task run is restarted
        $newTaskRun = TaskRunnerService::restart($targetTaskRun);

        // THEN: New TaskRun should have input artifacts from source node's output
        $this->assertEquals(2, $newTaskRun->inputArtifacts()->count(), 'New TaskRun should have input artifacts from source node');

        $sourceOutputIds = $sourceTaskRun->outputArtifacts()->pluck('artifacts.id')->sort()->values()->toArray();
        $newInputIds     = $newTaskRun->inputArtifacts()->pluck('artifacts.id')->sort()->values()->toArray();

        $this->assertEquals($sourceOutputIds, $newInputIds, 'New TaskRun input artifacts should match source node output artifacts');
    }

    #[Test]
    public function restart_new_run_has_reset_state(): void
    {
        // GIVEN: A failed task run with various state values
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id'       => $taskDefinition->id,
            'task_process_error_count' => 5,
            'restart_count'            => 2,
            'started_at'               => now()->subHour(),
            'failed_at'                => now(),
        ]);

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: New TaskRun has reset error count
        $this->assertEquals(0, $newTaskRun->task_process_error_count, 'Error count should be reset to 0');

        // AND: restart_count is incremented
        $this->assertEquals(3, $newTaskRun->restart_count, 'restart_count should be incremented');

        // AND: Timestamps are reset (pending state)
        $this->assertNull($newTaskRun->started_at, 'started_at should be null');
        $this->assertNull($newTaskRun->completed_at, 'completed_at should be null');
        $this->assertNull($newTaskRun->failed_at, 'failed_at should be null');
        $this->assertNull($newTaskRun->stopped_at, 'stopped_at should be null');
        $this->assertNull($newTaskRun->skipped_at, 'skipped_at should be null');
    }

    #[Test]
    public function restart_returns_new_task_run(): void
    {
        // GIVEN: A completed task run
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        // WHEN: The task run is restarted
        $result = TaskRunnerService::restart($taskRun);

        // THEN: The returned value is the NEW TaskRun
        $this->assertInstanceOf(TaskRun::class, $result);
        $this->assertNotEquals($taskRun->id, $result->id, 'Returned TaskRun should be the new one');
        $this->assertNull($result->deleted_at, 'Returned TaskRun should not be soft-deleted');
    }

    #[Test]
    public function restart_from_stopped_task_run_succeeds(): void
    {
        // GIVEN: A stopped task run
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'stopped_at'         => now(),
        ]);

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: Restart succeeds and creates new run
        $this->assertInstanceOf(TaskRun::class, $newTaskRun);
        $this->assertNotEquals($taskRun->id, $newTaskRun->id);
        $this->assertEquals(1, $newTaskRun->restart_count);
    }

    #[Test]
    public function restart_from_failed_task_run_succeeds(): void
    {
        // GIVEN: A failed task run
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'failed_at'          => now(),
        ]);

        // WHEN: The task run is restarted
        $newTaskRun = TaskRunnerService::restart($taskRun);

        // THEN: Restart succeeds and creates new run
        $this->assertInstanceOf(TaskRun::class, $newTaskRun);
        $this->assertNotEquals($taskRun->id, $newTaskRun->id);
        $this->assertEquals(1, $newTaskRun->restart_count);
    }

    #[Test]
    public function historical_runs_relationship_returns_soft_deleted_runs(): void
    {
        // GIVEN: A task run that has been restarted multiple times
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun1       = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'restart_count'      => 0,
            'started_at'         => now()->subHours(3),
            'completed_at'       => now()->subHours(2),
        ]);

        $taskRun2               = TaskRunnerService::restart($taskRun1);
        $taskRun2->started_at   = now()->subHours(2);
        $taskRun2->completed_at = now()->subHour();
        $taskRun2->save();

        $taskRun3 = TaskRunnerService::restart($taskRun2);

        // WHEN: Accessing historicalRuns relationship
        $historicalRuns = $taskRun3->historicalRuns;

        // THEN: Returns both soft-deleted runs ordered by most recent first
        $this->assertEquals(2, $historicalRuns->count(), 'Should have 2 historical runs');
        $this->assertEquals($taskRun2->id, $historicalRuns->first()->id, 'Most recent historical run should be first');
        $this->assertEquals($taskRun1->id, $historicalRuns->last()->id, 'Oldest historical run should be last');
    }

    #[Test]
    public function parent_run_relationship_returns_active_run(): void
    {
        // GIVEN: A task run that has been restarted
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun1       = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'started_at'         => now(),
            'completed_at'       => now(),
        ]);

        $taskRun2 = TaskRunnerService::restart($taskRun1);

        // Refresh to get updated parent_task_run_id
        $taskRun1->refresh();

        // WHEN: Accessing parentRun relationship on soft-deleted run
        $parentRun = $taskRun1->parentRun;

        // THEN: Returns the active run
        $this->assertNotNull($parentRun, 'parentRun should not be null');
        $this->assertEquals($taskRun2->id, $parentRun->id, 'parentRun should be the active run');
    }
}
