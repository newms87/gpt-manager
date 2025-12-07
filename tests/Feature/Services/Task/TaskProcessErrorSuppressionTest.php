<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskProcessErrorTrackingService;
use Exception;
use Illuminate\Http\Client\ConnectionException;
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

    public function test_retryable_errors_not_counted()
    {
        // Given - Create a TaskProcess
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors - mix of retryable and non-retryable
        $jobDispatch = $this->createJobDispatchWithMixedErrors(3, 2); // 3 non-retryable, 2 retryable
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Update error count
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - Only non-retryable errors are counted
        $taskProcess->refresh();
        $this->assertEquals(3, $taskProcess->error_count,
            'Only non-retryable errors should be counted (3 non-retryable, 2 retryable ignored)');
    }

    public function test_all_non_retryable_errors_counted()
    {
        // Given - Create a TaskProcess
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add only non-retryable errors
        $jobDispatch = $this->createJobDispatchWithErrors(7);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Update error count
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - All errors are counted
        $taskProcess->refresh();
        $this->assertEquals(7, $taskProcess->error_count,
            'All non-retryable errors should be counted');
    }

    public function test_failed_at_change_triggers_error_recount()
    {
        // Given - Create a TaskProcess with errors
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors
        $jobDispatch = $this->createJobDispatchWithErrors(4);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(4, $taskProcess->error_count,
            'Initial error count should be 4');

        // When - Mark it as failed
        $taskProcess->failed_at = now();
        $taskProcess->save();

        // Then - Error count should remain correct (via saved event listener)
        $taskProcess->refresh();
        $this->assertEquals(4, $taskProcess->error_count,
            'Error count should remain correct when process transitions to failed state');
        $this->assertTrue($taskProcess->isFailed(),
            'Process should be marked as failed');
    }

    public function test_error_count_cascades_to_task_run()
    {
        // Given - Create TaskDefinition with retry limit
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Create multiple TaskProcesses
        $taskProcess1 = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);
        $jobDispatch1 = $this->createJobDispatchWithErrors(5);
        $taskProcess1->jobDispatches()->attach($jobDispatch1->id);

        $taskProcess2 = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);
        $jobDispatch2 = $this->createJobDispatchWithErrors(4);
        $taskProcess2->jobDispatches()->attach($jobDispatch2->id);

        $taskProcess3 = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_FAILED,
        ]);
        $jobDispatch3 = $this->createJobDispatchWithErrors(3);
        $taskProcess3->jobDispatches()->attach($jobDispatch3->id);

        // When - Update error counts
        $errorTrackingService = app(TaskProcessErrorTrackingService::class);
        $errorTrackingService->updateTaskProcessErrorCount($taskProcess1);
        $errorTrackingService->updateTaskProcessErrorCount($taskProcess2);
        $errorTrackingService->updateTaskProcessErrorCount($taskProcess3);

        // Then - Verify TaskRun.task_process_error_count aggregates all errors
        $taskProcess1->refresh();
        $taskProcess2->refresh();
        $taskProcess3->refresh();
        $taskRun->refresh();

        $this->assertEquals(5, $taskProcess1->error_count);
        $this->assertEquals(4, $taskProcess2->error_count);
        $this->assertEquals(3, $taskProcess3->error_count);
        $this->assertEquals(12, $taskRun->task_process_error_count,
            'TaskRun should count all errors from all processes (5 + 4 + 3 = 12)');
    }

    public function test_restart_count_change_triggers_error_recount()
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
            'restart_count' => 2,
            'status'        => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add errors
        $jobDispatch = $this->createJobDispatchWithErrors(8);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(8, $taskProcess->error_count,
            'Error count should be 8');

        // When - Increment restart_count
        $taskProcess->restart_count = 3;
        $taskProcess->save();

        // Then - Error count should be recalculated via saved event
        $taskProcess->refresh();
        $this->assertEquals(8, $taskProcess->error_count,
            'Error count should remain correct after restart_count change (via saved event)');
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
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Multiple job dispatches with different error counts
        $jobDispatch1 = $this->createJobDispatchWithErrors(2);
        $jobDispatch2 = $this->createJobDispatchWithErrors(3);
        $jobDispatch3 = $this->createJobDispatchWithErrors(1);

        $taskProcess->jobDispatches()->attach($jobDispatch1->id);
        $taskProcess->jobDispatches()->attach($jobDispatch2->id);
        $taskProcess->jobDispatches()->attach($jobDispatch3->id);

        // When - Update error count
        $taskProcess->refresh();
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        // Then - Should aggregate all errors from all job dispatches
        $taskProcess->refresh();
        $this->assertEquals(6, $taskProcess->error_count,
            'Should aggregate errors from all job dispatches (2 + 3 + 1 = 6)');
    }

    public function test_is_retryable_column_is_queried_in_database()
    {
        // This test ensures the database schema and query are correct
        // If is_retryable column doesn't exist, this test will FAIL with SQL error

        // Given - Create a TaskProcess
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'             => $this->user->currentTeam->id,
            'max_process_retries' => 3,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        // Add mixed errors - 5 non-retryable, 3 retryable
        $jobDispatch = $this->createJobDispatchWithMixedErrors(5, 3);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Query directly tests the is_retryable column exists and is used
        $auditRequest = $jobDispatch->runningAuditRequest;

        // This query will throw SQL error if is_retryable column doesn't exist
        $nonRetryableCount = $auditRequest->errorLogEntries()
            ->where('is_retryable', false)
            ->count();

        $retryableCount = $auditRequest->errorLogEntries()
            ->where('is_retryable', true)
            ->count();

        // Then - Verify the query correctly filters by is_retryable
        $this->assertEquals(5, $nonRetryableCount,
            'Should count only non-retryable errors');
        $this->assertEquals(3, $retryableCount,
            'Should count only retryable errors');

        // Verify service uses the column correctly
        app(TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(5, $taskProcess->error_count,
            'Service should count only non-retryable errors (5), ignoring retryable (3)');
    }

    /**
     * Helper method to create a job dispatch with a specific number of non-retryable errors
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

        // Log non-retryable errors
        for ($i = 0; $i < $errorCount; $i++) {
            ErrorLog::logException(ErrorLog::ERROR, new Exception('Test error ' . ($i + 1)));
        }

        return $jobDispatch;
    }

    /**
     * Helper method to create a job dispatch with mix of retryable and non-retryable errors
     */
    private function createJobDispatchWithMixedErrors(int $nonRetryableCount, int $retryableCount): JobDispatch
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

        // Log non-retryable errors
        for ($i = 0; $i < $nonRetryableCount; $i++) {
            ErrorLog::logException(ErrorLog::ERROR, new Exception('Non-retryable error ' . ($i + 1)));
        }

        // Log retryable errors
        for ($i = 0; $i < $retryableCount; $i++) {
            ErrorLog::logException(ErrorLog::ERROR, new ConnectionException('Retryable error ' . ($i + 1)));
        }

        return $jobDispatch;
    }
}
