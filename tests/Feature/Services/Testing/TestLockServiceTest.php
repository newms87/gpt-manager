<?php

namespace Tests\Feature\Services\Testing;

use App\Services\Testing\TestLockService;
use RuntimeException;
use Tests\TestCase;

class TestLockServiceTest extends TestCase
{
    /**
     * Note: These tests verify the lock service behavior within an already-locked context
     * (since TestCase acquires a lock in setUpBeforeClass). The lock is already held
     * by the test suite, so we test that attempts to acquire a second lock fail as expected.
     */
    public function test_cannot_acquire_lock_when_already_locked(): void
    {
        // The test suite already has a lock via TestCase::setUpBeforeClass()
        // Try to acquire another lock - it should fail
        $secondLock = new TestLockService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot run tests: Another test suite is currently running/');

        $secondLock->acquireLock();
    }

    public function test_lock_error_message_includes_process_info(): void
    {
        // The test suite already has a lock via TestCase::setUpBeforeClass()
        $lockService = new TestLockService();

        try {
            $lockService->acquireLock();
            $this->fail('Should have thrown RuntimeException');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('PID:', $message);
            $this->assertStringContainsString('User:', $message);
            $this->assertStringContainsString('Started:', $message);
        }
    }

    public function test_stale_lock_is_removed_if_process_not_running(): void
    {
        // First, release the current test lock temporarily
        $currentLock = self::getTestLockService();
        $currentLock->releaseLock();

        try {
            // Create a lock file with a non-existent PID
            $lockFilePath = '/tmp/gpt-manager-tests.lock';

            $staleLockData = [
                'pid'        => 999999, // This PID should not exist
                'user'       => 'test',
                'started_at' => '2025-01-01 00:00:00',
            ];

            file_put_contents($lockFilePath, json_encode($staleLockData));

            $lockService = new TestLockService();

            // Should be able to acquire lock after removing stale lock
            $lockService->acquireLock();
            $this->assertNotNull($lockService->getLockFileHandle());

            // Clean up
            $lockService->releaseLock();
        } finally {
            // Re-acquire the lock for the test suite
            $currentLock->acquireLock();
        }
    }

    public function test_lock_service_is_active_during_test_execution(): void
    {
        // Verify that the lock service is active (held by TestCase)
        $lockService = self::getTestLockService();

        $this->assertNotNull($lockService);
        $this->assertNotNull($lockService->getLockFileHandle());
    }

    /**
     * Helper to access the protected $lockService property in TestCase
     */
    private static function getTestLockService(): TestLockService
    {
        $reflection = new \ReflectionClass(TestCase::class);
        $property   = $reflection->getProperty('lockService');
        $property->setAccessible(true);

        return $property->getValue();
    }
}
