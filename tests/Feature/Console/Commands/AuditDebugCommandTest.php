<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Newms87\Danx\Models\Audit\ApiLog;
use Newms87\Danx\Models\Audit\Audit;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Models\Audit\ErrorLog;
use Newms87\Danx\Models\Audit\ErrorLogEntry;
use Newms87\Danx\Models\Job\JobDispatch;
use Tests\TestCase;

/**
 * High-level tests for the audit:debug artisan command.
 *
 * Tests all major options and modes to ensure the command routes correctly
 * to the appropriate debug services and produces expected output.
 */
class AuditDebugCommandTest extends TestCase
{
    private User $user;

    private Team $team;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
    }

    // ===================================================================================
    // HELP AND USAGE TESTS
    // ===================================================================================

    public function test_shows_usage_help_when_no_arguments_provided(): void
    {
        $exitCode = Artisan::call('audit:debug');

        $this->assertEquals(Command::FAILURE, $exitCode);
        // When no args are provided, the command shows usage help and returns failure
    }

    public function test_help_option_displays_command_documentation(): void
    {
        $exitCode = Artisan::call('audit:debug', ['--help' => true]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('audit-request', $output);
        $this->assertStringContainsString('--overview', $output);
        $this->assertStringContainsString('--logs', $output);
        $this->assertStringContainsString('--api-logs', $output);
        $this->assertStringContainsString('--jobs', $output);
        $this->assertStringContainsString('--errors', $output);
        $this->assertStringContainsString('--audits', $output);
    }

    // ===================================================================================
    // RECENT LISTING TESTS
    // ===================================================================================

    public function test_recent_option_lists_recent_audit_requests(): void
    {
        $auditRequest = $this->createAuditRequest();

        $exitCode = Artisan::call('audit:debug', ['--recent' => 5]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString((string)$auditRequest->id, $output);
        $this->assertStringContainsString('Total:', $output);
    }

    public function test_recent_api_logs_option_lists_recent_api_logs(): void
    {
        $auditRequest = $this->createAuditRequest();
        $apiLog       = $this->createApiLog($auditRequest->id);

        $exitCode = Artisan::call('audit:debug', ['--recent-api-logs' => 5]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Recent API Logs', $output);
        $this->assertStringContainsString((string)$apiLog->id, $output);
    }

    public function test_recent_jobs_option_lists_recent_job_dispatches(): void
    {
        $auditRequest = $this->createAuditRequest();
        $jobDispatch  = $this->createJobDispatch($auditRequest->id);

        $exitCode = Artisan::call('audit:debug', ['--recent-jobs' => 5]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Recent Job Dispatches', $output);
        $this->assertStringContainsString((string)$jobDispatch->id, $output);
    }

    public function test_recent_errors_option_lists_recent_errors(): void
    {
        $errorLog = $this->createErrorLog();

        $exitCode = Artisan::call('audit:debug', ['--recent-errors' => 5]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Recent Errors', $output);
        $this->assertStringContainsString((string)$errorLog->id, $output);
    }

    // ===================================================================================
    // AUDIT REQUEST DETAIL TESTS
    // ===================================================================================

    public function test_overview_displays_audit_request_summary(): void
    {
        $auditRequest = $this->createAuditRequest(['url' => 'http://test.local/api/test']);

        $exitCode = Artisan::call('audit:debug', ['audit-request' => $auditRequest->id]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Audit Request #{$auditRequest->id}", $output);
        $this->assertStringContainsString('http://test.local/api/test', $output);
        $this->assertStringContainsString('Summary:', $output);
    }

    public function test_logs_option_displays_server_logs(): void
    {
        $auditRequest = $this->createAuditRequest(['logs' => "DEBUG Test debug message\nINFO Test info message"]);

        $exitCode = Artisan::call('audit:debug', [
            'audit-request' => $auditRequest->id,
            '--logs'        => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Request Logs', $output);
        $this->assertStringContainsString('Test debug message', $output);
        $this->assertStringContainsString('Test info message', $output);
    }

    public function test_api_logs_option_lists_api_logs_for_request(): void
    {
        $auditRequest = $this->createAuditRequest();
        $apiLog       = $this->createApiLog($auditRequest->id, ['service_name' => 'TestService']);

        $exitCode = Artisan::call('audit:debug', [
            'audit-request' => $auditRequest->id,
            '--api-logs'    => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('API Logs for Audit Request', $output);
        $this->assertStringContainsString('TestService', $output);
    }

    public function test_jobs_option_lists_job_dispatches_for_request(): void
    {
        $auditRequest = $this->createAuditRequest();
        $jobDispatch  = $this->createJobDispatch($auditRequest->id, ['name' => 'TestJobName']);

        $exitCode = Artisan::call('audit:debug', [
            'audit-request' => $auditRequest->id,
            '--jobs'        => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Job Dispatches for Audit Request', $output);
        $this->assertStringContainsString('TestJobName', $output);
    }

    public function test_errors_option_lists_error_entries_for_request(): void
    {
        $auditRequest = $this->createAuditRequest();
        $errorLog     = $this->createErrorLog(['error_class' => 'TestException']);
        $this->createErrorLogEntry($errorLog->id, $auditRequest->id);

        $exitCode = Artisan::call('audit:debug', [
            'audit-request' => $auditRequest->id,
            '--errors'      => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Errors for Audit Request', $output);
        $this->assertStringContainsString('TestException', $output);
    }

    public function test_audits_option_lists_model_changes_for_request(): void
    {
        $auditRequest = $this->createAuditRequest();
        $this->createAudit($auditRequest->id, [
            'auditable_type' => 'App\\Models\\TestModel',
            'event'          => 'created',
        ]);

        $exitCode = Artisan::call('audit:debug', [
            'audit-request' => $auditRequest->id,
            '--audits'      => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Model Changes for Audit Request', $output);
        $this->assertStringContainsString('created', $output);
    }

    // ===================================================================================
    // DIRECT DETAIL VIEW TESTS
    // ===================================================================================

    public function test_api_id_option_shows_api_log_detail(): void
    {
        $auditRequest = $this->createAuditRequest();
        $apiLog       = $this->createApiLog($auditRequest->id, [
            'service_name' => 'DetailTestService',
            'full_url'     => 'https://api.test.com/v1/endpoint',
        ]);

        $exitCode = Artisan::call('audit:debug', ['--api-id' => $apiLog->id]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("API Log #{$apiLog->id}", $output);
        $this->assertStringContainsString('DetailTestService', $output);
        $this->assertStringContainsString('Request Headers', $output);
        $this->assertStringContainsString('Response Headers', $output);
    }

    public function test_job_id_option_shows_job_dispatch_detail(): void
    {
        $auditRequest = $this->createAuditRequest();
        $jobDispatch  = $this->createJobDispatch($auditRequest->id, [
            'name'   => 'DetailTestJob',
            'status' => JobDispatch::STATUS_COMPLETE,
        ]);

        $exitCode = Artisan::call('audit:debug', ['--job-id' => $jobDispatch->id]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Job Dispatch #{$jobDispatch->id}", $output);
        $this->assertStringContainsString('DetailTestJob', $output);
        $this->assertStringContainsString('Timing:', $output);
    }

    public function test_error_id_option_shows_error_log_detail(): void
    {
        $errorLog = $this->createErrorLog([
            'error_class' => 'DetailTestException',
            'message'     => 'This is a detailed test error message',
        ]);

        $exitCode = Artisan::call('audit:debug', ['--error-id' => $errorLog->id]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Error Log #{$errorLog->id}", $output);
        $this->assertStringContainsString('DetailTestException', $output);
        $this->assertStringContainsString('Stack Trace', $output);
    }

    // ===================================================================================
    // FILTER TESTS
    // ===================================================================================

    public function test_api_service_filter_filters_by_service_name(): void
    {
        $auditRequest = $this->createAuditRequest();
        $this->createApiLog($auditRequest->id, ['service_name' => 'MatchingService']);
        $this->createApiLog($auditRequest->id, ['service_name' => 'OtherService']);

        $exitCode = Artisan::call('audit:debug', [
            '--recent-api-logs' => 10,
            '--api-service'     => 'Matching',
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('MatchingService', $output);
        $this->assertStringNotContainsString('OtherService', $output);
    }

    public function test_job_status_filter_filters_by_status(): void
    {
        $auditRequest = $this->createAuditRequest();
        $this->createJobDispatch($auditRequest->id, [
            'name'   => 'CompletedJob',
            'status' => JobDispatch::STATUS_COMPLETE,
        ]);
        $this->createJobDispatch($auditRequest->id, [
            'name'   => 'FailedJob',
            'status' => JobDispatch::STATUS_FAILED,
        ]);

        $exitCode = Artisan::call('audit:debug', [
            '--recent-jobs' => 10,
            '--job-status'  => 'Failed',
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('FailedJob', $output);
        $this->assertStringNotContainsString('CompletedJob', $output);
    }

    public function test_url_filter_filters_recent_requests_by_url(): void
    {
        $this->createAuditRequest(['url' => 'http://test.local/api/matching-endpoint']);
        $this->createAuditRequest(['url' => 'http://test.local/api/other-endpoint']);

        $exitCode = Artisan::call('audit:debug', [
            '--recent' => 10,
            '--url'    => 'matching',
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('matching-endpoint', $output);
        $this->assertStringNotContainsString('other-endpoint', $output);
    }

    // ===================================================================================
    // JSON OUTPUT TESTS
    // ===================================================================================

    public function test_json_option_outputs_valid_json_for_recent(): void
    {
        $this->createAuditRequest();

        $exitCode = Artisan::call('audit:debug', [
            '--recent' => 5,
            '--json'   => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('total', $decoded);
        $this->assertArrayHasKey('requests', $decoded);
    }

    public function test_json_option_outputs_valid_json_for_overview(): void
    {
        $auditRequest = $this->createAuditRequest();

        $exitCode = Artisan::call('audit:debug', [
            'audit-request' => $auditRequest->id,
            '--json'        => true,
        ]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('url', $decoded);
        $this->assertArrayHasKey('counts', $decoded);
    }

    // ===================================================================================
    // ERROR HANDLING TESTS
    // ===================================================================================

    public function test_returns_failure_for_nonexistent_audit_request(): void
    {
        $exitCode = Artisan::call('audit:debug', ['audit-request' => 999999]);

        $output = Artisan::output();
        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $output);
    }

    public function test_api_id_with_nonexistent_id_shows_error(): void
    {
        $exitCode = Artisan::call('audit:debug', ['--api-id' => 999999]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('not found', $output);
    }

    public function test_job_id_with_nonexistent_id_shows_error(): void
    {
        $exitCode = Artisan::call('audit:debug', ['--job-id' => 999999]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('not found', $output);
    }

    public function test_error_id_with_nonexistent_id_shows_error(): void
    {
        $exitCode = Artisan::call('audit:debug', ['--error-id' => 999999]);

        $output = Artisan::output();
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('not found', $output);
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
            'team_id'     => $this->team->id,
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
            'team_id'                  => $this->team->id,
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

    private function createAudit(int $auditRequestId, array $attributes = []): Audit
    {
        return Audit::create(array_merge([
            'audit_request_id' => $auditRequestId,
            'user_id'          => $this->user->id,
            'event'            => 'created',
            'auditable_type'   => 'App\\Models\\TestModel',
            'auditable_id'     => '1',
            'old_values'       => [],
            'new_values'       => ['name' => 'Test'],
        ], $attributes));
    }
}
