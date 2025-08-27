<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Newms87\Danx\Jobs\Job;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

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
