<?php

namespace Tests\Feature\Models\Audit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Newms87\Danx\Events\JobDispatchUpdatedEvent;
use Newms87\Danx\Models\Audit\ApiLog;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Models\Audit\ErrorLog;
use Newms87\Danx\Models\Audit\ErrorLogEntry;
use Newms87\Danx\Models\Job\JobDispatch;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class AuditRequestCountersTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function api_log_count_increments_when_api_log_created(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();

        // Verify initial count is 0
        $this->assertEquals(0, $auditRequest->api_log_count);

        // When
        $this->createApiLog($auditRequest->id);

        // Then
        $auditRequest->refresh();
        $this->assertEquals(1, $auditRequest->api_log_count);
    }

    #[Test]
    public function api_log_count_decrements_when_api_log_deleted(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();
        $apiLog       = $this->createApiLog($auditRequest->id);

        // Verify count is 1
        $auditRequest->refresh();
        $this->assertEquals(1, $auditRequest->api_log_count);

        // When
        $apiLog->delete();

        // Then
        $auditRequest->refresh();
        $this->assertEquals(0, $auditRequest->api_log_count);
    }

    #[Test]
    public function error_log_count_increments_when_error_log_entry_created(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();
        $errorLog     = $this->createErrorLog();

        // Verify initial count is 0
        $this->assertEquals(0, $auditRequest->error_log_count);

        // When
        $this->createErrorLogEntry($errorLog->id, $auditRequest->id);

        // Then
        $auditRequest->refresh();
        $this->assertEquals(1, $auditRequest->error_log_count);
    }

    #[Test]
    public function error_log_count_decrements_when_error_log_entry_deleted(): void
    {
        // Given
        $auditRequest  = $this->createAuditRequest();
        $errorLog      = $this->createErrorLog();
        $errorLogEntry = $this->createErrorLogEntry($errorLog->id, $auditRequest->id);

        // Verify count is 1
        $auditRequest->refresh();
        $this->assertEquals(1, $auditRequest->error_log_count);

        // When
        $errorLogEntry->delete();

        // Then
        $auditRequest->refresh();
        $this->assertEquals(0, $auditRequest->error_log_count);
    }

    #[Test]
    public function log_line_count_updates_when_logs_field_changes(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest(['logs' => '']);

        // Verify initial count is 0
        $auditRequest->refresh();
        $this->assertEquals(0, $auditRequest->log_line_count);

        // When - Update to 3 lines
        $auditRequest->update(['logs' => "line1\nline2\nline3"]);

        // Then
        $auditRequest->refresh();
        $this->assertEquals(3, $auditRequest->log_line_count);
    }

    #[Test]
    public function log_line_count_is_zero_for_empty_logs(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest(['logs' => "line1\nline2"]);
        $auditRequest->refresh();
        $this->assertEquals(2, $auditRequest->log_line_count);

        // When - Clear logs
        $auditRequest->update(['logs' => null]);

        // Then
        $auditRequest->refresh();
        $this->assertEquals(0, $auditRequest->log_line_count);
    }

    #[Test]
    public function job_dispatch_updated_event_fires_when_api_log_count_changes(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();
        $jobDispatch  = $this->createJobDispatch($auditRequest->id, [
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Release the lock acquired during JobDispatch creation so we can test the counter-triggered event
        $this->releaseJobDispatchLock($jobDispatch);

        $eventFired = false;

        Event::listen(JobDispatchUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $this->createApiLog($auditRequest->id);

        // Then
        $this->assertTrue($eventFired, 'JobDispatchUpdatedEvent should fire when api_log_count changes');
    }

    #[Test]
    public function job_dispatch_updated_event_fires_when_error_log_count_changes(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();
        $errorLog     = $this->createErrorLog();
        $jobDispatch  = $this->createJobDispatch($auditRequest->id, [
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Release the lock acquired during JobDispatch creation so we can test the counter-triggered event
        $this->releaseJobDispatchLock($jobDispatch);

        $eventFired = false;

        Event::listen(JobDispatchUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $this->createErrorLogEntry($errorLog->id, $auditRequest->id);

        // Then
        $this->assertTrue($eventFired, 'JobDispatchUpdatedEvent should fire when error_log_count changes');
    }

    #[Test]
    public function job_dispatch_updated_event_fires_when_log_line_count_changes(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest(['logs' => '']);
        $jobDispatch  = $this->createJobDispatch($auditRequest->id, [
            'status' => JobDispatch::STATUS_RUNNING,
            'ran_at' => now(),
        ]);

        // Release the lock acquired during JobDispatch creation so we can test the counter-triggered event
        $this->releaseJobDispatchLock($jobDispatch);

        $eventFired = false;

        Event::listen(JobDispatchUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $auditRequest->update(['logs' => "line1\nline2\nline3"]);

        // Then
        $this->assertTrue($eventFired, 'JobDispatchUpdatedEvent should fire when log_line_count changes');
    }

    #[Test]
    public function multiple_api_logs_increment_count_correctly(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();

        // When - Create 3 API logs
        $this->createApiLog($auditRequest->id);
        $this->createApiLog($auditRequest->id);
        $this->createApiLog($auditRequest->id);

        // Then
        $auditRequest->refresh();
        $this->assertEquals(3, $auditRequest->api_log_count);
    }

    #[Test]
    public function mixed_create_delete_operations_maintain_correct_count(): void
    {
        // Given
        $auditRequest = $this->createAuditRequest();

        // When - Create 3, delete 1
        $apiLog1 = $this->createApiLog($auditRequest->id);
        $this->createApiLog($auditRequest->id);
        $this->createApiLog($auditRequest->id);
        $apiLog1->delete();

        // Then
        $auditRequest->refresh();
        $this->assertEquals(2, $auditRequest->api_log_count);
    }

    // ===================================================================================
    // HELPER METHODS
    // ===================================================================================

    private function createAuditRequest(array $attributes = []): AuditRequest
    {
        return AuditRequest::create(array_merge([
            'url'         => 'http://test.local/api/test',
            'request'     => json_encode(['method' => 'GET']),
            'response'    => json_encode(['status' => 200]),
            'time'        => 0.5,
            'session_id'  => 'test-session-' . uniqid(),
            'user_id'     => $this->user->id,
            'team_id'     => $this->user->currentTeam->id,
            'logs'        => '',
            'environment' => 'testing',
        ], $attributes));
    }

    private function createApiLog(int $auditRequestId, array $attributes = []): ApiLog
    {
        return ApiLog::create(array_merge([
            'audit_request_id' => $auditRequestId,
            'service_name'     => 'TestService',
            'api_class'        => 'App\\Api\\TestApi',
            'method'           => 'POST',
            'url'              => '/v1/test',
            'full_url'         => 'https://api.test.com/v1/test',
            'status_code'      => 200,
            'request_headers'  => ['Content-Type' => 'application/json'],
            'request'          => ['test' => 'data'],
            'response_headers' => ['Content-Type' => 'application/json'],
            'response'         => ['result' => 'success'],
            'run_time_ms'      => 500,
            'started_at'       => now()->subSeconds(1),
            'finished_at'      => now(),
        ], $attributes));
    }

    private function createJobDispatch(int $auditRequestId, array $attributes = []): JobDispatch
    {
        return JobDispatch::create(array_merge([
            'running_audit_request_id' => $auditRequestId,
            'name'                     => 'TestJob',
            'ref'                      => 'job:test-' . uniqid(),
            'status'                   => JobDispatch::STATUS_PENDING,
            'count'                    => 1,
            'timeout_at'               => now()->addMinutes(5),
            'team_id'                  => $this->user->currentTeam->id,
        ], $attributes));
    }

    private function createErrorLog(array $attributes = []): ErrorLog
    {
        return ErrorLog::create(array_merge([
            'level'              => ErrorLog::ERROR,
            'error_class'        => 'TestException',
            'code'               => 0,
            'message'            => 'Test error message',
            'file'               => '/app/test.php',
            'line'               => 100,
            'stack_trace'        => [
                ['file' => '/app/test.php', 'line' => 100, 'function' => 'test', 'class' => 'TestClass'],
            ],
            'hash'               => hash('sha256', uniqid()),
            'count'              => 1,
            'send_notifications' => true,
            'last_seen_at'       => now(),
        ], $attributes));
    }

    private function createErrorLogEntry(int $errorLogId, int $auditRequestId): ErrorLogEntry
    {
        return ErrorLogEntry::create([
            'error_log_id'     => $errorLogId,
            'audit_request_id' => $auditRequestId,
            'is_retryable'     => false,
        ]);
    }

    /**
     * Release the cache lock held by JobDispatchUpdatedEvent for a JobDispatch.
     * This lock is acquired during JobDispatch creation/update to prevent duplicate events.
     * For testing counter-triggered events, we need to release it first.
     */
    private function releaseJobDispatchLock(JobDispatch $jobDispatch): void
    {
        $lockKey = JobDispatchUpdatedEvent::lockKey($jobDispatch);
        Cache::lock($lockKey)->forceRelease();
    }
}
