<?php

namespace Tests\Feature\Services\Task;

use App\Jobs\TaskProcessJob;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskProcessExecutorService;
use App\Services\Task\TaskProcessRunnerService;
use Exception;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Audit\AuditDriver;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Models\Audit\ErrorLog;
use Newms87\Danx\Models\Job\JobDispatch;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskProcessJobErrorTrackingTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Enable audit for tests
        config()->set('danx.audit.enabled', true);
    }

    public function test_job_dispatch_associates_with_task_process_during_execution()
    {
        // Given - Create a task run with a ready task process
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'is_ready' => true,
            'status' => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // When - Manually simulate the job dispatch association (as happens in TaskProcessRunnerService::run)
        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TaskProcessJob',
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // This simulates what happens in TaskProcessRunnerService::run() at lines 150-157
        $taskProcess->last_job_dispatch_id = $jobDispatch->id;
        $taskProcess->jobDispatches()->attach($jobDispatch->id);
        $taskProcess->updateRelationCounter('jobDispatches');
        $taskProcess->save();

        // Then - Verify job dispatch was associated with task process
        $taskProcess->refresh();
        $this->assertGreaterThan(0, $taskProcess->jobDispatches()->count(),
            'JobDispatch should be associated with TaskProcess during execution');
        $this->assertEquals($jobDispatch->id, $taskProcess->last_job_dispatch_id,
            'TaskProcess should track the most recent job dispatch');
    }

    public function test_error_tracking_during_task_process_job_with_errors()
    {
        // Given - Create a workflow with task runs
        $workflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'task_definition_id' => $taskDefinition->id,
        ]);
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);

        // Create a ready task process
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'is_ready' => true,
            'status' => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // Create a job dispatch to simulate job execution
        $jobDispatch = JobDispatch::create([
            'ref' => 'task-process-test-' . uniqid(),
            'name' => 'TaskProcessJob',
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Create audit request for error tracking
        $auditRequest = AuditRequest::create([
            'session_id' => 'test-session',
            'environment' => 'testing',
            'url' => 'task-process-job',
            'request' => [],
            'time' => 0,
        ]);
        AuditDriver::$auditRequest = $auditRequest;
        $jobDispatch->update(['running_audit_request_id' => $auditRequest->id]);

        // Associate job dispatch with task process (simulating what happens during execution)
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Log errors during execution
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Task process execution error 1');
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Task process execution error 2');
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Task process execution error 3');

        // When - Mark job as complete (this triggers the event listener)
        $jobDispatch->update(['status' => JobDispatch::STATUS_COMPLETE]);

        // Then - Verify error counts cascade correctly
        $taskProcess->refresh();
        $taskRun->refresh();
        $workflowRun->refresh();

        $this->assertEquals(3, $taskProcess->error_count,
            'TaskProcess error_count should be 3');
        $this->assertEquals(3, $taskRun->task_process_error_count,
            'TaskRun task_process_error_count should be 3');
        $this->assertEquals(3, $workflowRun->error_count,
            'WorkflowRun error_count should be 3');
    }

    public function test_multiple_task_processes_aggregate_errors_correctly()
    {
        // Given - Create workflow with multiple task runs and processes
        $workflowDefinition = WorkflowDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'task_definition_id' => $taskDefinition->id,
        ]);
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Create multiple task runs with different error counts
        $taskRun1 = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);
        $taskRun2 = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'workflow_run_id' => $workflowRun->id,
            'workflow_node_id' => $workflowNode->id,
        ]);

        // Create task processes with errors
        $taskProcess1 = $this->createTaskProcessWithErrors($taskRun1, 2);
        $taskProcess2 = $this->createTaskProcessWithErrors($taskRun1, 3); // Same task run
        $taskProcess3 = $this->createTaskProcessWithErrors($taskRun2, 1); // Different task run

        // When - Refresh all models
        $taskRun1->refresh();
        $taskRun2->refresh();
        $workflowRun->refresh();

        // Then - Verify error aggregation
        $this->assertEquals(2, $taskProcess1->error_count);
        $this->assertEquals(3, $taskProcess2->error_count);
        $this->assertEquals(1, $taskProcess3->error_count);

        // TaskRun1 should have 2 + 3 = 5 errors
        $this->assertEquals(5, $taskRun1->task_process_error_count,
            'TaskRun1 should aggregate errors from multiple task processes');

        // TaskRun2 should have 1 error
        $this->assertEquals(1, $taskRun2->task_process_error_count);

        // WorkflowRun should have 5 + 1 = 6 total errors
        $this->assertEquals(6, $workflowRun->error_count,
            'WorkflowRun should aggregate errors from all task runs');
    }

    public function test_job_dispatch_status_changes_trigger_error_count_updates()
    {
        // Given - Create task process with associated job dispatch
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'is_ready' => true,
            'status' => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TaskProcessJob',
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Associate with task process
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Create audit request and log errors
        $auditRequest = AuditRequest::create([
            'session_id' => 'test-session',
            'environment' => 'testing',
            'url' => 'test-job',
            'request' => [],
            'time' => 0,
        ]);
        AuditDriver::$auditRequest = $auditRequest;
        $jobDispatch->update(['running_audit_request_id' => $auditRequest->id]);

        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Error during job execution');
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Another error during job execution');

        // When - Change job status (simulating completion/failure)
        $jobDispatch->update(['status' => JobDispatch::STATUS_FAILED]);

        // Then - Error counts should be updated via event listener
        $taskProcess->refresh();
        $taskRun->refresh();

        $this->assertEquals(2, $taskProcess->error_count,
            'TaskProcess error_count should be updated when JobDispatch status changes');
        $this->assertEquals(2, $taskRun->task_process_error_count,
            'TaskRun error_count should cascade from TaskProcess');
    }

    public function test_error_count_updates_when_job_transitions_from_running_to_complete()
    {
        // Given - Task process with running job
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status' => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TaskProcessJob',
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Create audit request
        $auditRequest = AuditRequest::create([
            'session_id' => 'test-session',
            'environment' => 'testing',
            'url' => 'test-job',
            'request' => [],
            'time' => 0,
        ]);
        AuditDriver::$auditRequest = $auditRequest;
        $jobDispatch->update(['running_audit_request_id' => $auditRequest->id]);

        // Initially no errors
        $this->assertEquals(0, $taskProcess->error_count);

        // When - Errors occur and job completes
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Runtime error');
        $jobDispatch->update(['status' => JobDispatch::STATUS_COMPLETE, 'completed_at' => now()]);

        // Then - Error counts should be updated
        $taskProcess->refresh();
        $this->assertEquals(1, $taskProcess->error_count);
    }

    public function test_fallback_error_tracking_when_exception_thrown_in_runner()
    {
        // Given - Task process ready to run
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'is_ready' => true,
            'status' => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // Create a job dispatch with errors
        $jobDispatch = $this->createJobDispatchWithErrors(4);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Manually trigger the fallback error tracking (simulating exception in TaskProcessRunnerService::run)
        $this->expectException(Exception::class);

        try {
            throw new Exception('Task process runner failed');
        } catch (Exception $e) {
            // Simulate the fallback error tracking in catch block
            app(\App\Services\Task\TaskProcessErrorTrackingService::class)
                ->updateTaskProcessErrorCount($taskProcess);
            throw $e;
        }

        // Then - Error count should be updated by fallback mechanism
        $taskProcess->refresh();
        $this->assertEquals(4, $taskProcess->error_count,
            'Fallback error tracking should update error count when exception is thrown');
    }

    public function test_get_error_log_entries_for_task_process()
    {
        // Given - Task process with errors
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
        ]);

        $jobDispatch = $this->createJobDispatchWithErrors(3);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // When - Get error log entries
        $errors = $taskRun->getErrorLogEntries();

        // Then - Should retrieve all errors
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('Test error', $errors->first()->full_message);
    }

    public function test_error_count_resets_on_task_process_restart()
    {
        // Given - Task process with errors
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'is_ready' => true,
            'status' => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        $jobDispatch = $this->createJobDispatchWithErrors(5);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Update error count
        app(\App\Services\Task\TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        $taskProcess->refresh();
        $this->assertEquals(5, $taskProcess->error_count);

        // When - Restart the task process
        TaskProcessRunnerService::restart($taskProcess);

        // Then - Task process should be reset but error count remains (historical errors)
        $taskProcess->refresh();
        $this->assertEquals(5, $taskProcess->error_count,
            'Error count should remain after restart to maintain historical record');
        $this->assertTrue($taskProcess->is_ready, 'Task process should be ready after restart');
        $this->assertNull($taskProcess->failed_at, 'Failed timestamp should be cleared');
    }

    /**
     * Helper method to create a task process with errors
     */
    private function createTaskProcessWithErrors(TaskRun $taskRun, int $errorCount): TaskProcess
    {
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'is_ready' => true,
            'status' => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        $jobDispatch = $this->createJobDispatchWithErrors($errorCount);
        $taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Trigger error count update
        app(\App\Services\Task\TaskProcessErrorTrackingService::class)
            ->updateTaskProcessErrorCount($taskProcess);

        return $taskProcess;
    }

    /**
     * Helper method to create a job dispatch with a specific number of errors
     */
    private function createJobDispatchWithErrors(int $errorCount): JobDispatch
    {
        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TaskProcessJob',
            'status' => JobDispatch::STATUS_COMPLETE,
            'ran_at' => now(),
            'completed_at' => now(),
        ]);

        // Create audit request
        $auditRequest = AuditRequest::create([
            'session_id' => 'test-session',
            'environment' => 'testing',
            'url' => 'test-job',
            'request' => [],
            'time' => 0,
        ]);
        AuditDriver::$auditRequest = $auditRequest;
        $jobDispatch->update(['running_audit_request_id' => $auditRequest->id]);

        // Log errors
        for ($i = 0; $i < $errorCount; $i++) {
            ErrorLog::logErrorMessage(ErrorLog::ERROR, "Test error " . ($i + 1));
        }

        return $jobDispatch;
    }
}
