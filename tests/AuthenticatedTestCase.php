<?php

namespace Tests;

use App\Models\User;
use Newms87\Danx\Jobs\Job;

abstract class AuthenticatedTestCase extends TestCase
{
    protected User $user;

    public function setUp(): void
    {
        parent::setUp();
        Job::$runningJob         = null;
        $this->user              = User::factory()->create();
        $this->user->currentTeam = $this->user->teams()->first();
        $this->actingAs($this->user);
    }
}
