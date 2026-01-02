<?php

namespace Tests\Feature\Models\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AuthenticatedTestCase;

class TaskProcessStatusComputationTest extends AuthenticatedTestCase
{
    use RefreshDatabase;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();

        $this->taskDefinition = TaskDefinition::factory()->create([
            'max_process_retries' => 2,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    public function test_incomplete_process_that_can_be_retried_has_incomplete_status(): void
    {
        // Given: A process that failed incomplete but can be retried (restart_count < max_process_retries)
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'incomplete_at'  => now(),
            'restart_count'  => 0, // Can be retried (0 < 2)
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should be "Incomplete" (not "Failed") because it can be retried
        $this->assertEquals(WorkflowStatesContract::STATUS_INCOMPLETE, $process->status);
        $this->assertTrue($process->canBeRetried());
    }

    public function test_incomplete_process_that_cannot_be_retried_has_failed_status(): void
    {
        // Given: A process that failed incomplete and cannot be retried (restart_count >= max_process_retries)
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'incomplete_at'  => now(),
            'restart_count'  => 2, // Cannot be retried (2 >= 2)
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should be "Failed" because retries are exhausted
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $process->status);
        $this->assertFalse($process->canBeRetried());
    }

    public function test_timeout_process_that_can_be_retried_has_timeout_status(): void
    {
        // Given: A process that timed out but can be retried
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'timeout_at'     => now(),
            'restart_count'  => 0, // Can be retried (0 < 2)
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should be "Timeout" (not "Failed") because it can be retried
        $this->assertEquals(WorkflowStatesContract::STATUS_TIMEOUT, $process->status);
        $this->assertTrue($process->canBeRetried());
    }

    public function test_timeout_process_that_cannot_be_retried_has_failed_status(): void
    {
        // Given: A process that timed out and cannot be retried
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'timeout_at'     => now(),
            'restart_count'  => 2, // Cannot be retried (2 >= 2)
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should be "Failed" because retries are exhausted
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $process->status);
        $this->assertFalse($process->canBeRetried());
    }

    public function test_failed_process_always_has_failed_status(): void
    {
        // Given: A process that explicitly failed
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'failed_at'      => now(),
            'restart_count'  => 0,
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should always be "Failed"
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $process->status);
    }

    public function test_task_run_with_failed_process_has_failed_status(): void
    {
        // Given: A task run with one failed process (retries exhausted)
        TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'incomplete_at'  => now(),
            'restart_count'  => 2, // Cannot be retried
        ]);

        // When: Task run checks processes
        $this->taskRun->checkProcesses();

        // Then: Task run should be marked as failed
        $this->assertNotNull($this->taskRun->failed_at);
        $this->assertNull($this->taskRun->completed_at);
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $this->taskRun->status);
    }

    public function test_task_run_with_incomplete_retryable_process_is_not_failed(): void
    {
        // Given: A task run with an incomplete but retryable process
        TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'incomplete_at'  => now(),
            'restart_count'  => 0, // Can be retried
        ]);

        // When: Task run checks processes
        $this->taskRun->checkProcesses();

        // Then: Task run should NOT be marked as failed (process is still incomplete, not failed)
        $this->assertNull($this->taskRun->failed_at);
        $this->assertNull($this->taskRun->completed_at);
        $this->assertEquals(WorkflowStatesContract::STATUS_RUNNING, $this->taskRun->status);
    }

    public function test_updating_incomplete_at_recomputes_status_based_on_retries(): void
    {
        // Given: A process that is retryable
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'restart_count'  => 0,
        ]);

        // When: We mark it incomplete
        $process->update(['incomplete_at' => now()]);
        $process->refresh();

        // Then: Status should be Incomplete (can be retried)
        $this->assertEquals(WorkflowStatesContract::STATUS_INCOMPLETE, $process->status);

        // When: We exhaust retries and save to trigger status recomputation
        $process->restart_count = 2;
        $process->save();
        $process->refresh();

        // Then: Status should automatically become Failed (cannot be retried)
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $process->status);
    }

    public function test_completed_process_has_completed_status_regardless_of_retries(): void
    {
        // Given: A completed process
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'completed_at'   => now(),
            'restart_count'  => 1,
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should be Completed
        $this->assertEquals(WorkflowStatesContract::STATUS_COMPLETED, $process->status);
    }

    public function test_stopped_process_has_stopped_status_regardless_of_retries(): void
    {
        // Given: A stopped process
        $process = TaskProcess::factory()->create([
            'task_run_id'    => $this->taskRun->id,
            'started_at'     => now()->subMinutes(5),
            'stopped_at'     => now(),
            'restart_count'  => 0,
        ]);

        // When: Status is computed
        $process->refresh();

        // Then: Status should be Stopped
        $this->assertEquals(WorkflowStatesContract::STATUS_STOPPED, $process->status);
    }
}
