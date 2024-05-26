<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Newms87\Danx\Jobs\Job;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        // Always reset all jobs to enabled in case a previous test disabled something
        Job::enableAll();
        $this->artisan('migrate');
        $this->artisan('db:seed --class=TestingSeeder');
    }
}
