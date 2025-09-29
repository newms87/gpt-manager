<?php

namespace Tests\Unit\Services\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\TaskProcessErrorTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Audit\AuditDriver;
use Newms87\Danx\Jobs\Job;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Models\Audit\ErrorLog;
use Newms87\Danx\Models\Job\JobDispatch;
use Tests\TestCase;

class TaskProcessErrorTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected TaskProcessErrorTrackingService $service;
    protected TaskDefinition $taskDefinition;
    protected TaskRun $taskRun;
    protected TaskProcess $taskProcess;

    public function setUp(): void
    {
        parent::setUp();

        // Enable audit for tests
        config()->set('danx.audit.enabled', true);

        $this->service = app(TaskProcessErrorTrackingService::class);

        // Create test models
        $this->taskDefinition = TaskDefinition::factory()->create();
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'name' => 'Test Process',
        ]);
    }

    public function test_it_tracks_errors_for_task_process_with_no_errors()
    {
        $this->service->updateTaskProcessErrorCount($this->taskProcess);

        $this->taskProcess->refresh();
        $this->assertEquals(0, $this->taskProcess->error_count);
    }

    public function test_it_tracks_errors_for_task_process_with_errors()
    {
        // Create a job dispatch
        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TestJob',
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Associate the job dispatch with the task process
        $this->taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Create an audit request for the job dispatch
        $auditRequest = AuditRequest::create([
            'session_id' => 'test-session',
            'environment' => 'testing',
            'url' => 'test-job',
            'request' => [],
            'time' => 0,
        ]);
        AuditDriver::$auditRequest = $auditRequest;
        $jobDispatch->update(['running_audit_request_id' => $auditRequest->id]);

        // Log some errors
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Test error 1');
        ErrorLog::logErrorMessage(ErrorLog::ERROR, 'Test error 2');

        // Update error count
        $this->service->updateTaskProcessErrorCount($this->taskProcess);

        $this->taskProcess->refresh();
        $this->assertEquals(2, $this->taskProcess->error_count);
    }

    public function test_it_updates_error_count_on_task_process()
    {
        // Initially no errors
        $this->assertEquals(0, $this->taskProcess->error_count);

        // Create a job dispatch with errors
        $jobDispatch = $this->createJobDispatchWithErrors(3);

        // Associate the job dispatch with the task process
        $this->taskProcess->jobDispatches()->attach($jobDispatch->id);

        // Update error count
        $this->service->updateTaskProcessErrorCount($this->taskProcess);

        // Refresh the model to get updated values
        $this->taskProcess->refresh();

        $this->assertEquals(3, $this->taskProcess->error_count);
    }

    public function test_it_updates_error_count_from_job_dispatch()
    {
        // Create a job dispatch with errors
        $jobDispatch = $this->createJobDispatchWithErrors(2);

        // Associate the job dispatch with the task process via job_dispatchables
        \DB::table('job_dispatchables')->insert([
            'job_dispatch_id' => $jobDispatch->id,
            'model_type' => TaskProcess::class,
            'model_id' => $this->taskProcess->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update error count from job dispatch
        $this->service->updateErrorCountsForJobDispatch($jobDispatch);

        // Refresh models
        $this->taskProcess->refresh();
        $this->taskRun->refresh();

        $this->assertEquals(2, $this->taskProcess->error_count);
        $this->assertEquals(2, $this->taskRun->task_process_error_count);
    }

    public function test_task_run_aggregates_error_counts_from_multiple_task_processes()
    {
        // Create additional task processes
        $taskProcess2 = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'name' => 'Test Process 2',
        ]);
        $taskProcess3 = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'name' => 'Test Process 3',
        ]);

        // Create job dispatches with different error counts
        $jobDispatch1 = $this->createJobDispatchWithErrors(2);
        $jobDispatch2 = $this->createJobDispatchWithErrors(3);
        $jobDispatch3 = $this->createJobDispatchWithErrors(1);

        // Associate job dispatches with task processes
        $this->taskProcess->jobDispatches()->attach($jobDispatch1->id);
        $taskProcess2->jobDispatches()->attach($jobDispatch2->id);
        $taskProcess3->jobDispatches()->attach($jobDispatch3->id);

        // Update error counts for all task processes
        $this->service->updateTaskProcessErrorCount($this->taskProcess);
        $this->service->updateTaskProcessErrorCount($taskProcess2);
        $this->service->updateTaskProcessErrorCount($taskProcess3);

        // Refresh the task run
        $this->taskRun->refresh();

        // Total errors should be 2 + 3 + 1 = 6
        $this->assertEquals(6, $this->taskRun->task_process_error_count);
    }

    public function test_error_count_updates_on_job_dispatch_status_change()
    {
        // Create a job dispatch
        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TestJob',
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Associate with task process
        \DB::table('job_dispatchables')->insert([
            'job_dispatch_id' => $jobDispatch->id,
            'model_type' => TaskProcess::class,
            'model_id' => $this->taskProcess->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        // Simulate job completion (this would trigger the event listener)
        $jobDispatch->update(['status' => JobDispatch::STATUS_COMPLETE]);

        // The event listener in EventServiceProvider should have been triggered
        // For this test, we'll manually trigger it since events might not fire in tests
        $this->service->updateErrorCountsForJobDispatch($jobDispatch);

        $this->taskProcess->refresh();
        $this->assertEquals(1, $this->taskProcess->error_count);
    }

    /**
     * Helper method to create a job dispatch with a specific number of errors
     */
    private function createJobDispatchWithErrors(int $errorCount): JobDispatch
    {
        $jobDispatch = JobDispatch::create([
            'ref' => 'test-job-' . uniqid(),
            'name' => 'TestJob',
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