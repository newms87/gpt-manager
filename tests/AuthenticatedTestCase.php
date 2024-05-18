<?php

namespace Tests;

use App\Models\User;

abstract class AuthenticatedTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $user = User::first();
        $this->actingAs($user);
    }
}
