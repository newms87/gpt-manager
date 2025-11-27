<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskProcessErrorTrackingService;
use Newms87\Danx\Audit\AuditDriver;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Models\Audit\ErrorLog;
use Newms87\Danx\Models\Job\JobDispatch;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskProcessErrorSuppressionTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Enable audit for tests
        config()->set('danx.audit.enabled', true);
    }

    public function test_errors_suppressed_when_process_can_be_retried()
    {
        // Given - Create a TaskProcess with restart_count = 1 and TaskDefinition with max_process_retries = 3
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 1,
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors via JobDispatch → AuditRequest → ErrorLogEntries
        $jobDispatch = $this->createJobDispatchWithErrors(5);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Update error count (simulating what happens during job execution)
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - Assert error_count = 0 (suppressed because can retry)
        $taskProcess->refresh();
        $this->assertEquals(0, $taskProcess->error_count,
            'Errors should be suppressed when process can be retried (restart_count=1 < max_retries=3)');
        $this->assertTrue($taskProcess->canBeRetried(),
            'Process should be retriable');
    }

    public function test_errors_surfaced_when_retries_exhausted()
    {
        // Given - Create a TaskProcess with restart_count = 3 and TaskDefinition with max_process_retries = 3
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 3, // Exhausted retries
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors
        $jobDispatch = $this->createJobDispatchWithErrors(7);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Update error count
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - Assert error_count = actual count (errors should now be visible)
        $taskProcess->refresh();
        $this->assertEquals(7, $taskProcess->error_count,
            'Errors should be visible when retries are exhausted (restart_count=3 >= max_retries=3)');
        $this->assertFalse($taskProcess->canBeRetried(),
            'Process should not be retriable when retries exhausted');
    }

    public function test_errors_surface_on_transition_to_failed()
    {
        // Given - Create a retriable TaskProcess with errors (error_count starts at 0)
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 1, // Can still retry
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors (but they should be suppressed initially)
        $jobDispatch = $this->createJobDispatchWithErrors(4);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(0, $taskProcess->error_count,
            'Errors should be initially suppressed when process can be retried');

        // When - Mark it as failed
        $taskProcess->failed_at = now();
        $taskProcess->save();

        // Then - Error count should update to actual count (via saved event listener)
        $taskProcess->refresh();
        $this->assertEquals(4, $taskProcess->error_count,
            'Errors should surface when process transitions to failed state');
        $this->assertTrue($taskProcess->isFailed(),
            'Process should be marked as failed');
    }

    public function test_permanent_failure_shows_errors_immediately()
    {
        // Given - Create a TaskProcess with restart_count exhausted from the start
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 2,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 2, // Already at max retries
            'failed_at'     => now(),
            'status'        => WorkflowStatesContract::STATUS_FAILED,
        ]);

        // Add errors
        $jobDispatch = $this->createJobDispatchWithErrors(3);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Update error count
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - Verify errors are visible immediately
        $taskProcess->refresh();
        $this->assertEquals(3, $taskProcess->error_count,
            'Errors should be visible immediately for permanently failed process');
        $this->assertFalse($taskProcess->canBeRetried(),
            'Process should not be retriable');
        $this->assertTrue($taskProcess->isFailed(),
            'Process should be failed');
    }

    public function test_error_count_remains_zero_on_successful_completion()
    {
        // Given - Create a TaskProcess that had errors during retry attempts
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 2, // Had to retry
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors from previous failed attempts
        $jobDispatch = $this->createJobDispatchWithErrors(6);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Initially errors should be suppressed (can still retry)
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(0, $taskProcess->error_count,
            'Errors should be suppressed while process can still retry');

        // When - Mark it as successfully completed
        $taskProcess->completed_at = now();
        $taskProcess->save();

        // Then - Error count should stay 0 (success means errors don't matter)
        $taskProcess->refresh();
        $this->assertEquals(0, $taskProcess->error_count,
            'Error count should remain 0 on successful completion - success trumps previous errors');
        $this->assertTrue($taskProcess->isCompleted(),
            'Process should be completed');
    }

    public function test_error_suppression_cascades_to_task_run()
    {
        // Given - Create TaskDefinition with retry limit
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Create multiple TaskProcesses - some retriable, some not
        // TaskProcess 1: Can be retried (restart_count=1 < max=3) - errors suppressed
        $taskProcess1 = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 1,
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);
        $jobDispatch1 = $this->createJobDispatchWithErrors(5);
        $taskProcess1->jobDispatches()->attach($jobDispatch1->id);

        // TaskProcess 2: Cannot be retried (restart_count=3 >= max=3) - errors visible
        $taskProcess2 = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 3,
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);
        $jobDispatch2 = $this->createJobDispatchWithErrors(4);
        $taskProcess2->jobDispatches()->attach($jobDispatch2->id);

        // TaskProcess 3: Failed and cannot be retried - errors visible
        $taskProcess3 = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 2,
            'failed_at'     => now(),
            'status'        => WorkflowStatesContract::STATUS_FAILED,
        ]);
        $jobDispatch3 = $this->createJobDispatchWithErrors(3);
        $taskProcess3->jobDispatches()->attach($jobDispatch3->id);

        // When - Update error counts
        $errorTrackingService = app(TaskProcessErrorTrackingService::class);
        $errorTrackingService->updateTaskProcessErrorCount($taskProcess1);
        $errorTrackingService->updateTaskProcessErrorCount($taskProcess2);
        $errorTrackingService->updateTaskProcessErrorCount($taskProcess3);

        // Then - Verify TaskRun.task_process_error_count only includes non-retriable processes
        $taskProcess1->refresh();
        $taskProcess2->refresh();
        $taskProcess3->refresh();
        $taskRun->refresh();

        $this->assertEquals(0, $taskProcess1->error_count,
            'TaskProcess1 errors should be suppressed (retriable)');
        $this->assertEquals(4, $taskProcess2->error_count,
            'TaskProcess2 errors should be visible (retries exhausted)');
        $this->assertEquals(3, $taskProcess3->error_count,
            'TaskProcess3 errors should be visible (failed)');
        $this->assertEquals(7, $taskRun->task_process_error_count,
            'TaskRun should only count errors from non-retriable processes (4 + 3 = 7)');
    }

    public function test_restart_count_increment_triggers_error_recount()
    {
        // Given - Create a TaskProcess at the edge of retry limit
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 2, // One retry left
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors
        $jobDispatch = $this->createJobDispatchWithErrors(8);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(0, $taskProcess->error_count,
            'Errors should be suppressed with one retry left');

        // When - Increment restart_count to exhaust retries
        $taskProcess->restart_count = 3; // Now at max
        $taskProcess->save();

        // Then - Error count should be recalculated via saved event
        $taskProcess->refresh();
        $this->assertEquals(8, $taskProcess->error_count,
            'Errors should surface when restart_count reaches max (via saved event)');
    }

    public function test_multiple_job_dispatches_aggregate_errors_correctly()
    {
        // Given - Create a TaskProcess that ran multiple times with different error counts
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 1,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'restart_count' => 1, // At max retries
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Multiple job dispatches with different error counts
        $jobDispatch1 = $this->createJobDispatchWithErrors(2);
        $jobDispatch2 = $this->createJobDispatchWithErrors(3);
        $jobDispatch3 = $this->createJobDispatchWithErrors(1);

        $taskProcess->jobDispatches()->attach($jobDispatch1->id);
        $taskProcess->jobDispatches()->attach($jobDispatch2->id);
        $taskProcess->jobDispatches()->attach($jobDispatch3->id);

        // When - Update error count
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - Should aggregate all errors from all job dispatches
        $taskProcess->refresh();
        $this->assertEquals(6, $taskProcess->error_count,
            'Should aggregate errors from all job dispatches (2 + 3 + 1 = 6)');
    }

    /**
     * Helper method to create a job dispatch with a specific number of errors
     */
    private function createJobDispatchWithErrors(int $errorCount): JobDispatch
    {
        $jobDispatch = JobDispatch::create([
            'ref'          => 'test-job-' . uniqid(),
            'name'         => 'TaskProcessJob',
            'status'       => JobDispatch::STATUS_COMPLETE,
            'ran_at'       => now(),
            'completed_at' => now(),
        ]);

        // Create audit request
        $auditRequest = AuditRequest::create([
            'session_id'  => 'test-session-' . uniqid(),
            'environment' => 'testing',
            'url'         => 'test-job',
            'request'     => [],
            'time'        => 0,
        ]);
        AuditDriver::$auditRequest = $auditRequest;
        $jobDispatch->update(['running_audit_request_id' => $auditRequest->id]);

        // Log errors
        for ($i = 0; $i < $errorCount; $i++) {
            ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Test error ' . ($i + 1));
        }

        return $jobDispatch;
    }
}
