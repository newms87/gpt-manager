<?php

namespace Tests;

use Flytedan\DanxLaravel\Jobs\Job;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        // Always reset all jobs to enabled in case a previous test disabled something
        Job::enableAll();
        $this->artisan('db:seed --class=TestingSeeder');
    }
}
