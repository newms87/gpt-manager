<?php

namespace Tests;

use App\Services\Testing\TestLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Newms87\Danx\Jobs\Job;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private static ?TestLockService $lockService = null;

    /**
     * Acquire the test lock before any test class runs
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$lockService = new TestLockService();
        self::$lockService->acquireLock();
    }

    /**
     * Release the test lock after all tests in the class complete
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$lockService !== null) {
            self::$lockService->releaseLock();
            self::$lockService = null;
        }

        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        parent::setUp();

        // CRITICAL: Ensure tests only run on databases containing "testing" in the name
        $currentDb = config('database.connections.pgsql.database');
        if (stripos($currentDb, 'testing') === false) {
            throw new \RuntimeException(
                "CRITICAL: Tests must use a database with 'testing' in the name. Current database: {$currentDb}"
            );
        }

        // Always reset all jobs to enabled in case a previous test disabled something
        Job::enableAll();
    }

    /**
     * Disable automatic seeding for tests to avoid performance issues
     */
    protected bool $seed = false;
}
