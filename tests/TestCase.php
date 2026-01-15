<?php

namespace Tests;

use App\Services\Testing\TestLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Newms87\Danx\Jobs\Job;
use Tests\Feature\Api\TestAi\TestAiApi;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Standard test model name - all tests should use this */
    public const string TEST_MODEL = 'test-model';

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

        // Configure test model for all tests
        $this->configureTestModel();

        // Always reset all jobs to enabled in case a previous test disabled something
        Job::enableAll();
    }

    /**
     * Configure the test model with TestAiApi for all tests.
     * This provides a consistent, mock AI API for testing.
     */
    protected function configureTestModel(): void
    {
        Config::set('ai.models.' . self::TEST_MODEL, [
            'api'          => TestAiApi::class,
            'name'         => 'Test Model',
            'context'      => 128_000,
            'input'        => 1.00 / 1_000_000,
            'cached_input' => 0.10 / 1_000_000,
            'output'       => 2.00 / 1_000_000,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'reasoning'          => true,
            ],
            'rate_limits' => [
                'tokens_per_minute'   => 100_000,
                'requests_per_minute' => 100,
            ],
        ]);

        // Set test-model as default
        Config::set('ai.default_model', self::TEST_MODEL);
    }

    /**
     * Disable automatic seeding for tests to avoid performance issues
     */
    protected bool $seed = false;
}
