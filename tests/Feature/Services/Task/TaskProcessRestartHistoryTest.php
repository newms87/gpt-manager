<?php

namespace Tests\Feature\Services\Task;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

/**
 * Tests for the task process restart history feature.
 *
 * This feature tracks task process restart history by cloning processes on restart
 * instead of mutating them. Old processes are soft-deleted and linked to the new
 * active process via parent_task_process_id.
 */
class TaskProcessRestartHistoryTest extends AuthenticatedTestCase
{
    // ============================================================================
    // Clone Creates Correct New Process
    // ============================================================================

    #[Test]
    public function restart_creates_new_process_with_incremented_restart_count(): void
    {
        // Given: A failed task process with restart_count = 0
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: New process should have restart_count = 1
        $this->assertEquals(1, $newProcess->restart_count, 'New process restart_count should be old + 1');
        $this->assertNotEquals($oldProcess->id, $newProcess->id, 'Should be a new process');
    }

    #[Test]
    public function restart_creates_new_process_with_reset_agent_thread(): void
    {
        // Given: A failed task process with an agent thread
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $agentThread    = AgentThread::factory()->create();
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'        => true,
            'agent_thread_id' => $agentThread->id,
            'status'          => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'       => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: New process should have NULL agent_thread_id
        $this->assertNull($newProcess->agent_thread_id, 'New process should have NULL agent_thread_id');
    }

    #[Test]
    public function restart_creates_new_process_with_correct_relationships(): void
    {
        // Given: A failed task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'name'      => 'Test Process',
            'operation' => 'test-operation',
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: New process should have correct relationships
        $this->assertEquals($taskRun->id, $newProcess->task_run_id, 'New process should have same task_run_id');
        $this->assertEquals('Test Process', $newProcess->name, 'New process should have same name');
        $this->assertEquals('test-operation', $newProcess->operation, 'New process should have same operation');
    }

    #[Test]
    public function restart_creates_new_process_with_reset_status(): void
    {
        // Given: A failed task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'     => true,
            'status'       => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'    => now(),
            'started_at'   => now()->subMinute(),
            'completed_at' => null,
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: New process should be ready and pending
        $this->assertTrue($newProcess->is_ready, 'New process should be ready');
        $this->assertEquals(0, $newProcess->percent_complete, 'New process should have 0 percent complete');
        $this->assertNull($newProcess->started_at, 'New process should have NULL started_at');
        $this->assertNull($newProcess->completed_at, 'New process should have NULL completed_at');
        $this->assertNull($newProcess->failed_at, 'New process should have NULL failed_at');
    }

    // ============================================================================
    // Old Process is Soft-Deleted with Correct Parent
    // ============================================================================

    #[Test]
    public function restart_soft_deletes_old_process(): void
    {
        // Given: A failed task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // When: The process is restarted
        TaskProcessRunnerService::restart($oldProcess);

        // Then: Old process should be soft-deleted
        $oldProcess->refresh();
        $this->assertTrue($oldProcess->trashed(), 'Old process should be soft-deleted');
    }

    #[Test]
    public function restart_links_old_process_to_new_process_via_parent_task_process_id(): void
    {
        // Given: A failed task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: Old process should have parent_task_process_id pointing to new process
        $oldProcess->refresh();
        $this->assertEquals($newProcess->id, $oldProcess->parent_task_process_id, 'Old process parent_task_process_id should point to new process');
    }

    #[Test]
    public function restart_preserves_old_process_job_dispatches(): void
    {
        // Given: A failed task process with job dispatches
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'           => true,
            'status'             => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'          => now(),
            'job_dispatch_count' => 2,
        ]);

        // When: The process is restarted
        TaskProcessRunnerService::restart($oldProcess);

        // Then: Old process should preserve its job_dispatch_count
        $oldProcess->refresh();
        $this->assertEquals(2, $oldProcess->job_dispatch_count, 'Old process should preserve job_dispatch_count');
    }

    #[Test]
    public function restart_preserves_old_process_output_artifacts(): void
    {
        // Given: A failed task process with output artifacts
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);
        $artifacts = Artifact::factory()->count(3)->create();
        $oldProcess->outputArtifacts()->sync($artifacts->pluck('id'));
        $oldProcess->updateRelationCounter('outputArtifacts');

        // When: The process is restarted
        TaskProcessRunnerService::restart($oldProcess);

        // Then: Old process should preserve its output artifacts
        $oldProcess->refresh();
        $this->assertEquals(3, $oldProcess->outputArtifacts()->count(), 'Old process should preserve output artifacts');
    }

    #[Test]
    public function restart_preserves_old_process_agent_thread(): void
    {
        // Given: A failed task process with an agent thread
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $agentThread    = AgentThread::factory()->create();
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'        => true,
            'agent_thread_id' => $agentThread->id,
            'status'          => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'       => now(),
        ]);

        // When: The process is restarted
        TaskProcessRunnerService::restart($oldProcess);

        // Then: Old process should preserve its agent_thread_id
        $oldProcess->refresh();
        $this->assertEquals($agentThread->id, $oldProcess->agent_thread_id, 'Old process should preserve agent_thread_id');
    }

    // ============================================================================
    // Multiple Restarts Update All Historical References (Flat Chain)
    // ============================================================================

    #[Test]
    public function multiple_restarts_maintain_flat_chain_structure(): void
    {
        // Given: A failed task process
        $taskDefinition  = TaskDefinition::factory()->create();
        $taskRun         = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
        ]);

        // When: First restart
        $firstNewProcess = TaskProcessRunnerService::restart($originalProcess);

        // And: Second restart (simulate failure and restart again)
        $firstNewProcess->failed_at = now();
        $firstNewProcess->save();
        $secondNewProcess = TaskProcessRunnerService::restart($firstNewProcess);

        // Then: Original process should point to the newest process (flat chain)
        $originalProcess->refresh();
        $this->assertEquals($secondNewProcess->id, $originalProcess->parent_task_process_id, 'Original process should point to newest process');

        // And: First new process should also point to the newest process (flat chain)
        $firstNewProcess->refresh();
        $this->assertEquals($secondNewProcess->id, $firstNewProcess->parent_task_process_id, 'First new process should point to newest process');

        // And: Second new process should have no parent (it's the active one)
        $this->assertNull($secondNewProcess->parent_task_process_id, 'Newest active process should have no parent');
    }

    #[Test]
    public function multiple_restarts_increment_restart_count_correctly(): void
    {
        // Given: A failed task process
        $taskDefinition  = TaskDefinition::factory()->create();
        $taskRun         = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
        ]);

        // When: Multiple restarts
        $firstNewProcess = TaskProcessRunnerService::restart($originalProcess);

        $firstNewProcess->failed_at = now();
        $firstNewProcess->save();
        $secondNewProcess = TaskProcessRunnerService::restart($firstNewProcess);

        $secondNewProcess->failed_at = now();
        $secondNewProcess->save();
        $thirdNewProcess = TaskProcessRunnerService::restart($secondNewProcess);

        // Then: Restart counts should be correct
        $this->assertEquals(0, $originalProcess->restart_count, 'Original process restart_count should be 0');
        $this->assertEquals(1, $firstNewProcess->restart_count, 'First new process restart_count should be 1');
        $this->assertEquals(2, $secondNewProcess->restart_count, 'Second new process restart_count should be 2');
        $this->assertEquals(3, $thirdNewProcess->restart_count, 'Third new process restart_count should be 3');
    }

    #[Test]
    public function all_historical_processes_point_to_current_active_after_multiple_restarts(): void
    {
        // Given: A series of restarts creating historical processes
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);

        $originalProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
        ]);

        // Perform three restarts
        $process1            = TaskProcessRunnerService::restart($originalProcess);
        $process1->failed_at = now();
        $process1->save();

        $process2            = TaskProcessRunnerService::restart($process1);
        $process2->failed_at = now();
        $process2->save();

        $activeProcess = TaskProcessRunnerService::restart($process2);

        // Then: All historical processes should point to the active process
        $this->assertEquals($activeProcess->id, $originalProcess->fresh()->parent_task_process_id);
        $this->assertEquals($activeProcess->id, $process1->fresh()->parent_task_process_id);
        $this->assertEquals($activeProcess->id, $process2->fresh()->parent_task_process_id);
        $this->assertNull($activeProcess->parent_task_process_id, 'Active process should have no parent');
    }

    // ============================================================================
    // Historical Processes Relationship
    // ============================================================================

    #[Test]
    public function historical_processes_relationship_returns_soft_deleted_children(): void
    {
        // Given: An active process with historical (soft-deleted) processes
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);

        $originalProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
        ]);

        $process1            = TaskProcessRunnerService::restart($originalProcess);
        $process1->failed_at = now();
        $process1->save();

        $activeProcess = TaskProcessRunnerService::restart($process1);

        // When: We query historical processes on the active process
        $historicalProcesses = $activeProcess->historicalProcesses;

        // Then: Should return 2 soft-deleted processes
        $this->assertCount(2, $historicalProcesses, 'Should have 2 historical processes');
        $this->assertTrue($historicalProcesses->contains('id', $originalProcess->id), 'Should contain original process');
        $this->assertTrue($historicalProcesses->contains('id', $process1->id), 'Should contain first new process');
    }

    #[Test]
    public function historical_processes_relationship_orders_by_created_at_desc(): void
    {
        // Given: An active process with historical processes created at different times
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);

        $originalProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
            'created_at'    => now()->subMinutes(10),
        ]);

        $process1            = TaskProcessRunnerService::restart($originalProcess);
        $process1->failed_at = now();
        $process1->save();

        $activeProcess = TaskProcessRunnerService::restart($process1);

        // When: We query historical processes
        $historicalProcesses = $activeProcess->historicalProcesses;

        // Then: Should be ordered by created_at DESC (most recent first)
        $this->assertCount(2, $historicalProcesses);
        // The first restart (process1) was created after original, so it should come first
        $this->assertEquals($process1->id, $historicalProcesses->first()->id, 'Most recent historical process should come first');
    }

    #[Test]
    public function historical_processes_relationship_does_not_include_non_soft_deleted_processes(): void
    {
        // Given: An active process with no historical processes yet
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $activeProcess  = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => true,
            'status'   => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // When: We query historical processes
        $historicalProcesses = $activeProcess->historicalProcesses;

        // Then: Should be empty
        $this->assertCount(0, $historicalProcesses, 'Should have no historical processes');
    }

    #[Test]
    public function historical_processes_relationship_returns_empty_collection_when_no_history(): void
    {
        // Given: A fresh task process with no restart history
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskProcess    = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => true,
        ]);

        // When: We query historical processes
        $historicalProcesses = $taskProcess->historicalProcesses;

        // Then: Should be empty collection
        $this->assertCount(0, $historicalProcesses);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $historicalProcesses);
    }

    // ============================================================================
    // Parent Process Relationship
    // ============================================================================

    #[Test]
    public function parent_process_relationship_returns_active_process_for_historical_processes(): void
    {
        // Given: A historical process that points to an active process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);

        $originalProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'restart_count' => 0,
            'status'        => WorkflowStatesContract::STATUS_FAILED,
            'failed_at'     => now(),
        ]);

        $activeProcess = TaskProcessRunnerService::restart($originalProcess);

        // When: We query parent process on the historical process
        $originalProcess->refresh();
        $parentProcess = $originalProcess->parentProcess;

        // Then: Should return the active process
        $this->assertNotNull($parentProcess, 'Historical process should have a parent process');
        $this->assertEquals($activeProcess->id, $parentProcess->id, 'Parent should be the active process');
    }

    #[Test]
    public function parent_process_relationship_returns_null_for_active_processes(): void
    {
        // Given: An active process (no restarts)
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $activeProcess  = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => true,
        ]);

        // When: We query parent process
        $parentProcess = $activeProcess->parentProcess;

        // Then: Should be null
        $this->assertNull($parentProcess, 'Active process should have no parent process');
    }

    // ============================================================================
    // Input Artifacts are Synced
    // ============================================================================

    #[Test]
    public function restart_syncs_input_artifacts_to_new_process(): void
    {
        // Given: A failed task process with input artifacts
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);
        $artifacts = Artifact::factory()->count(3)->create();
        $oldProcess->inputArtifacts()->sync($artifacts->pluck('id'));
        $oldProcess->updateRelationCounter('inputArtifacts');

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: New process should have the same input artifacts
        $this->assertEquals(3, $newProcess->inputArtifacts()->count(), 'New process should have same input artifact count');
        $this->assertEquals(
            $oldProcess->inputArtifacts()->pluck('artifacts.id')->sort()->values()->toArray(),
            $newProcess->inputArtifacts()->pluck('artifacts.id')->sort()->values()->toArray(),
            'New process should have same input artifact IDs (synced, not copied)'
        );
    }

    #[Test]
    public function restart_uses_sync_not_copy_for_input_artifacts(): void
    {
        // Given: A failed task process with input artifacts
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);
        $artifact = Artifact::factory()->create();
        $oldProcess->inputArtifacts()->sync([$artifact->id]);
        $oldProcess->updateRelationCounter('inputArtifacts');

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: The artifact should be the SAME record (same ID), not a copy
        $newArtifactIds = $newProcess->inputArtifacts()->pluck('artifacts.id')->toArray();
        $oldArtifactIds = $oldProcess->inputArtifacts()->pluck('artifacts.id')->toArray();

        $this->assertEquals($oldArtifactIds, $newArtifactIds, 'Should reference same artifacts, not copies');
        $this->assertContains($artifact->id, $newArtifactIds, 'Should contain the original artifact ID');
    }

    // ============================================================================
    // Schema Association Copying
    // ============================================================================

    #[Test]
    public function restart_copies_output_schema_association_to_new_process(): void
    {
        // Given: A failed task process with a schema association
        $taskDefinition    = TaskDefinition::factory()->withSchemaDefinition()->create();
        $taskRun           = TaskRunnerService::prepareTaskRun($taskDefinition);
        $schemaAssociation = SchemaAssociation::factory()
            ->withSchema($taskDefinition->schemaDefinition)
            ->create();

        $oldProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => true,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // Manually create the schema association for the process
        $schemaAssociation->replicate()->forceFill([
            'object_id'   => $oldProcess->id,
            'object_type' => TaskProcess::class,
            'category'    => 'output',
        ])->save();

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then: New process should have a schema association
        $this->assertNotNull($newProcess->outputSchemaAssociation, 'New process should have output schema association');
        $this->assertEquals(
            $oldProcess->outputSchemaAssociation->schema_fragment_id,
            $newProcess->outputSchemaAssociation->schema_fragment_id,
            'New process schema association should have same schema_fragment_id'
        );
        // But should be a different record
        $this->assertNotEquals(
            $oldProcess->outputSchemaAssociation->id,
            $newProcess->outputSchemaAssociation->id,
            'New process should have a NEW schema association record'
        );
    }

    // ============================================================================
    // Edge Cases and Validation
    // ============================================================================

    #[Test]
    public function restart_throws_validation_error_when_process_is_running(): void
    {
        // Given: A running task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $runningProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'   => true,
            'started_at' => now(),
            'status'     => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Then: Expect validation error
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('TaskProcess is currently running, cannot restart');

        // When: Attempting to restart
        TaskProcessRunnerService::restart($runningProcess);
    }

    #[Test]
    public function restart_works_for_incomplete_process(): void
    {
        // Given: An incomplete task process
        $taskDefinition    = TaskDefinition::factory()->create();
        $taskRun           = TaskRunnerService::prepareTaskRun($taskDefinition);
        $incompleteProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'status'        => WorkflowStatesContract::STATUS_INCOMPLETE,
            'incomplete_at' => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($incompleteProcess);

        // Then: Should succeed
        $this->assertNotNull($newProcess);
        $this->assertNotEquals($incompleteProcess->id, $newProcess->id);
        $this->assertTrue($incompleteProcess->fresh()->trashed());
    }

    #[Test]
    public function restart_works_for_timeout_process(): void
    {
        // Given: A timed out task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $timeoutProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'   => true,
            'status'     => WorkflowStatesContract::STATUS_TIMEOUT,
            'timeout_at' => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($timeoutProcess);

        // Then: Should succeed
        $this->assertNotNull($newProcess);
        $this->assertNotEquals($timeoutProcess->id, $newProcess->id);
        $this->assertTrue($timeoutProcess->fresh()->trashed());
    }

    #[Test]
    public function restart_works_for_stopped_process(): void
    {
        // Given: A stopped task process
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $stoppedProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'   => true,
            'status'     => WorkflowStatesContract::STATUS_STOPPED,
            'stopped_at' => now(),
        ]);

        // When: The process is restarted
        $newProcess = TaskProcessRunnerService::restart($stoppedProcess);

        // Then: Should succeed
        $this->assertNotNull($newProcess);
        $this->assertNotEquals($stoppedProcess->id, $newProcess->id);
        $this->assertTrue($stoppedProcess->fresh()->trashed());
    }
}
