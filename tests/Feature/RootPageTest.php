<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RootPageTest extends TestCase
{
    public function test_appRedirectsToLoginForUnauthenticatedUser(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }

    public function test_appReturnsSuccessfulResponseForAuthUser(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
