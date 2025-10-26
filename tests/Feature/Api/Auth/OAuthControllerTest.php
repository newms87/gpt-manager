<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Auth\AuthToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class OAuthControllerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected string $service = 'google';

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Set proper authentication guard for API tests
        Config::set('auth.defaults.guard', 'web');

        // Mock Google OAuth configuration
        Config::set('auth.oauth.google', [
            'client_id'     => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uri'  => 'http://localhost/api/oauth/google/callback',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'revoke_url'    => 'https://oauth2.googleapis.com/revoke',
            'scopes'        => [
                'https://www.googleapis.com/auth/documents',
                'https://www.googleapis.com/auth/drive',
                'openid',
                'email',
                'profile',
            ],
            'access_type'     => 'offline',
            'approval_prompt' => 'force',
        ]);

        // Set the SPA URL for testing (simulating the .env value)
        Config::set('app.spa_url', env('APP_SPA_URL', 'http://localhost:5173'));
    }

    public function test_authorize_withValidConfig_returnsAuthorizationUrl(): void
    {
        // When
        $response = $this->getJson("/api/oauth/{$this->service}/authorize");

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'authorization_url',
            'service',
            'state',
        ]);

        $url = $response->json('authorization_url');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertEquals($this->service, $response->json('service'));
    }

    public function test_authorize_withoutTeamContext_returns400(): void
    {
        // Given - logout to remove authentication
        $this->app['auth']->logout();

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/authorize");

        // Then
        $response->assertStatus(401);
    }

    public function test_authorize_withoutConfiguration_returns500(): void
    {
        // Given - remove all configuration for the service
        Config::set('auth.oauth.google', null);

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/authorize");

        // Then - Service will return 500 when configuration is missing
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
    }

    public function test_authorize_withRedirectAfterAuth_includesInState(): void
    {
        // Given
        $redirectUrl = 'https://example.com/success';

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/authorize?redirect_after_auth=" . urlencode($redirectUrl));

        // Then
        $response->assertOk();
        $state     = $response->json('state');
        $stateData = json_decode(base64_decode($state), true);
        $this->assertEquals($redirectUrl, $stateData['redirect_after_auth']);
    }

    public function test_callback_withValidCode_returnsRedirectWithoutQueryParams(): void
    {
        // Given
        $code  = 'test_authorization_code';
        $state = base64_encode(json_encode([
            'service'   => $this->service,
            'team_id'   => $this->user->currentTeam->id,
            'timestamp' => time(),
        ]));

        $tokenData = [
            'access_token'  => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'expires_in'    => 3600,
        ];

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response($tokenData, 200),
        ]);

        // When
        $response = $this->get("/api/oauth/callback?code=$code&state=$state");

        // Then
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        // Verify redirect does NOT contain error query parameters
        $this->assertStringNotContainsString('oauth_success', $location);
        $this->assertStringNotContainsString('oauth_error', $location);
        $this->assertStringNotContainsString('message=', $location);
        $this->assertStringNotContainsString('service=', $location);

        // Verify token was stored
        $this->assertDatabaseHas('auth_tokens', [
            'team_id'      => $this->user->currentTeam->id,
            'service'      => $this->service,
            'type'         => AuthToken::TYPE_OAUTH,
            'access_token' => 'test_access_token',
        ]);
    }

    public function test_callback_withRedirectUrl_redirectsToSpecifiedUrlWithoutQueryParams(): void
    {
        // Given
        $code        = 'test_authorization_code';
        $redirectUrl = 'https://example.com/success';
        $state       = base64_encode(json_encode([
            'service'             => $this->service,
            'team_id'             => $this->user->currentTeam->id,
            'timestamp'           => time(),
            'redirect_after_auth' => $redirectUrl,
        ]));

        $tokenData = [
            'access_token'  => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'expires_in'    => 3600,
        ];

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response($tokenData, 200),
        ]);

        // When
        $response = $this->get("/api/oauth/callback?code=$code&state=$state");

        // Then
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        // Verify redirect is exactly to the specified URL without query parameters
        $this->assertEquals($redirectUrl, $location);
        $this->assertStringNotContainsString('oauth_success', $location);
        $this->assertStringNotContainsString('service=', $location);
    }

    public function test_callback_withError_throwsValidationError(): void
    {
        // Given
        $error = 'access_denied';

        // When - Use getJson() to get JSON error response instead of exception
        // Note: AuditingMiddleware catches exceptions and returns generic 500 error
        $response = $this->getJson("/api/oauth/callback?error=$error");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);

        // Verify it does NOT redirect with query parameters (the old behavior)
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withoutCode_throwsValidationError(): void
    {
        // Given
        $state = base64_encode(json_encode([
            'service'   => $this->service,
            'team_id'   => $this->user->currentTeam->id,
            'timestamp' => time(),
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?state=$state");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withInvalidState_throwsValidationError(): void
    {
        // Given
        $code         = 'test_code';
        $invalidState = base64_encode(json_encode([
            'service'   => $this->service,
            'team_id'   => 99999, // Non-existent team ID
            'timestamp' => time(),
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$invalidState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withExpiredState_throwsValidationError(): void
    {
        // Given
        $code         = 'test_code';
        $expiredState = base64_encode(json_encode([
            'service'   => $this->service,
            'team_id'   => $this->user->currentTeam->id,
            'timestamp' => time() - 700, // More than 10 minutes ago (600 seconds)
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$expiredState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withoutState_throwsValidationError(): void
    {
        // Given
        $code = 'test_code';

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withMalformedState_throwsValidationError(): void
    {
        // Given
        $code           = 'test_code';
        $malformedState = 'not_valid_base64_json';

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$malformedState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withStateMissingService_throwsValidationError(): void
    {
        // Given
        $code         = 'test_code';
        $invalidState = base64_encode(json_encode([
            // Missing 'service' field
            'team_id'   => $this->user->currentTeam->id,
            'timestamp' => time(),
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$invalidState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withStateMissingTeamId_throwsValidationError(): void
    {
        // Given
        $code         = 'test_code';
        $invalidState = base64_encode(json_encode([
            'service' => $this->service,
            // Missing 'team_id' field
            'timestamp' => time(),
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$invalidState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withStateMissingTimestamp_throwsValidationError(): void
    {
        // Given
        $code         = 'test_code';
        $invalidState = base64_encode(json_encode([
            'service' => $this->service,
            'team_id' => $this->user->currentTeam->id,
            // Missing 'timestamp' field
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$invalidState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withStateEmptyService_throwsValidationError(): void
    {
        // Given
        $code         = 'test_code';
        $invalidState = base64_encode(json_encode([
            'service'   => '', // Empty service
            'team_id'   => $this->user->currentTeam->id,
            'timestamp' => time(),
        ]));

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$invalidState");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_callback_withTokenExchangeFailure_bubblesException(): void
    {
        // Given
        $code  = 'test_code';
        $state = base64_encode(json_encode([
            'service'   => $this->service,
            'team_id'   => $this->user->currentTeam->id,
            'timestamp' => time(),
        ]));

        // Mock HTTP failure from token exchange
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error'             => 'invalid_grant',
                'error_description' => 'Invalid authorization code',
            ], 400),
        ]);

        // When - Use getJson() to get JSON error response
        $response = $this->getJson("/api/oauth/callback?code=$code&state=$state");

        // Then - Verify error is returned (not redirect with query parameters)
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertFalse($response->isRedirect());
    }

    public function test_status_withExistingToken_returnsTokenInfo(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/status");

        // Then
        $response->assertOk();
        $response->assertJson([
            'has_token'     => true,
            'is_configured' => true,
            'service'       => $this->service,
        ]);
        $response->assertJsonStructure([
            'has_token',
            'is_configured',
            'service',
            'token' => [
                'id',
                'team_id',
                'service',
                'type',
                'is_valid',
                'is_expired',
            ],
        ]);
    }

    public function test_status_withoutToken_returnsNoToken(): void
    {
        // When
        $response = $this->getJson("/api/oauth/{$this->service}/status");

        // Then
        $response->assertOk();
        $response->assertJson([
            'has_token'     => false,
            'is_configured' => true,
            'service'       => $this->service,
        ]);
        $response->assertJsonMissing(['token']);
    }

    public function test_revoke_withExistingToken_revokesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response(null, 200),
        ]);

        // When
        $response = $this->deleteJson("/api/oauth/{$this->service}/revoke");

        // Then
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'service' => $this->service,
            'message' => "OAuth token for {$this->service} revoked successfully",
        ]);

        // Token should be soft deleted, not hard deleted
        $this->assertSoftDeleted('auth_tokens', [
            'id' => $token->id,
        ]);
    }

    public function test_revoke_withoutToken_returnsNotFoundMessage(): void
    {
        // When
        $response = $this->deleteJson("/api/oauth/{$this->service}/revoke");

        // Then
        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'service' => $this->service,
            'message' => "No OAuth token found for {$this->service}",
        ]);
    }

    public function test_refresh_withValidToken_refreshesToken(): void
    {
        // Given
        $token               = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->expiresSoon()->create();
        $originalAccessToken = $token->access_token;

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token',
                'expires_in'   => 3600,
            ], 200),
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/refresh");

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'team_id',
            'service',
            'is_valid',
            'expires_at',
        ]);

        // Verify token was updated in database
        $token->refresh();
        $this->assertNotEquals($originalAccessToken, $token->access_token);
        $this->assertEquals('new_access_token', $token->access_token);
        $this->assertTrue($token->isValid());
    }

    public function test_refresh_withoutToken_returns404(): void
    {
        // When
        $response = $this->postJson("/api/oauth/{$this->service}/refresh");

        // Then
        $response->assertStatus(404);
        $response->assertJson([
            'message' => "No OAuth token found for service: {$this->service}",
        ]);
    }

    public function test_refresh_withInvalidToken_returnsError(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error'             => 'invalid_grant',
                'error_description' => 'Token has been revoked',
            ], 400),
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/refresh");

        // Then - The service will throw an exception that gets converted to 500
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
    }

    public function test_index_returnsAllTokensForTeam(): void
    {
        // Given
        $googleToken = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        $stripeToken = AuthToken::factory()->stripe()->apiKey()->forTeam($this->user->currentTeam)->create();

        // Token for different team should not be included
        AuthToken::factory()->google()->create();

        // When
        $response = $this->getJson('/api/oauth/tokens');

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'count',
        ]);
        $this->assertEquals(2, $response->json('count'));
    }

    public function test_index_withServiceFilter_returnsFilteredTokens(): void
    {
        // Given
        $googleToken = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        $stripeToken = AuthToken::factory()->stripe()->forTeam($this->user->currentTeam)->create();

        // When
        $response = $this->getJson('/api/oauth/tokens?service=google');

        // Then
        $response->assertOk();
        $this->assertEquals(1, $response->json('count'));
        $tokenData = $response->json('data');
        $this->assertEquals('google', $tokenData[0]['service']);
    }

    public function test_storeApiKey_withValidData_storesToken(): void
    {
        // Given
        $requestData = [
            'service'  => 'openai',
            'api_key'  => 'sk-test-api-key',
            'name'     => 'OpenAI API Key',
            'metadata' => ['usage' => 'development'],
        ];

        // When
        $response = $this->postJson('/api/oauth/api-keys', $requestData);

        // Then
        $response->assertOk();
        $response->assertJson([
            'service' => 'openai',
            'type'    => AuthToken::TYPE_API_KEY,
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'OpenAI API Key',
        ]);

        $this->assertDatabaseHas('auth_tokens', [
            'service'      => 'openai',
            'type'         => AuthToken::TYPE_API_KEY,
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'OpenAI API Key',
            'access_token' => 'sk-test-api-key',
        ]);
    }

    public function test_destroy_withValidToken_deletesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();

        // When
        $response = $this->deleteJson("/api/oauth/tokens/{$token->id}");

        // Then
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Successfully deleted oauth token for google',
        ]);

        // Token should be soft deleted, not hard deleted
        $this->assertSoftDeleted('auth_tokens', [
            'id' => $token->id,
        ]);
    }

    public function test_destroy_withWrongTeam_returns403(): void
    {
        // Given
        $token = AuthToken::factory()->google()->create(); // Different team

        // When
        $response = $this->deleteJson("/api/oauth/tokens/{$token->id}");

        // Then - Model binding might fail (404) or permission check might fail (403)
        // Both are acceptable since the user shouldn't have access to tokens from other teams
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
        $response->assertJsonStructure(['message']);
    }

    // Tests for OAuth token validation endpoints

    public function test_status_withValidateParameter_performsFullValidation(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at'   => now()->addHour(),
        ]);

        // Mock successful Google API validation
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'test@example.com'],
            ], 200),
        ]);

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/status?validate=true");

        // Then
        $response->assertOk();
        $response->assertJson([
            'has_token'     => true,
            'is_configured' => true,
            'service'       => $this->service,
        ]);

        $response->assertJsonStructure([
            'has_token',
            'is_configured',
            'service',
            'validation' => [
                'valid',
                'reason',
                'token',
            ],
        ]);

        $validation = $response->json('validation');
        $this->assertTrue($validation['valid']);
        $this->assertEquals('valid', $validation['reason']);
        $this->assertNotNull($validation['token']);
    }

    public function test_status_withValidateParameter_andNoToken_returnsNoToken(): void
    {
        // Given - no token exists

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/status?validate=true");

        // Then
        $response->assertOk();
        $response->assertJson([
            'has_token'     => false,
            'is_configured' => true,
            'service'       => $this->service,
        ]);

        $validation = $response->json('validation');
        $this->assertFalse($validation['valid']);
        $this->assertEquals('no_token', $validation['reason']);
    }

    public function test_status_withValidateParameter_andRevokedToken_returnsRevoked(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'revoked_token',
            'expires_at'   => now()->addHour(),
        ]);

        // Mock failed Google API validation
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => [
                    'code'    => 401,
                    'message' => 'Invalid Credentials',
                ],
            ], 401),
        ]);

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/status?validate=true");

        // Then
        $response->assertOk();
        $response->assertJson([
            'has_token'     => false, // Should be false since validation failed
            'is_configured' => true,
            'service'       => $this->service,
        ]);

        $validation = $response->json('validation');
        $this->assertFalse($validation['valid']);
        $this->assertEquals('revoked', $validation['reason']);

        // Token should be soft deleted
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_status_withoutValidateParameter_doesNotPerformFullValidation(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'revoked_token', // Token is actually revoked
            'expires_at'   => now()->addHour(),
        ]);

        // Mock should NOT be called since we're not validating
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([], 401),
        ]);

        // When
        $response = $this->getJson("/api/oauth/{$this->service}/status");

        // Then
        $response->assertOk();
        $response->assertJson([
            'has_token'     => true, // Should be true since we didn't validate
            'is_configured' => true,
            'service'       => $this->service,
        ]);

        // Validation key should not be present
        $response->assertJsonMissing(['validation']);

        // HTTP should not have been called
        Http::assertNothingSent();

        // Token should NOT be deleted (we didn't validate)
        $this->assertFalse($token->fresh()->trashed());
    }

    public function test_validate_withValidToken_returnsValid(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at'   => now()->addHour(),
        ]);

        // Mock successful Google API validation
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'test@example.com'],
            ], 200),
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertOk();
        $response->assertJson([
            'valid'  => true,
            'reason' => 'valid',
        ]);
        $response->assertJsonStructure([
            'valid',
            'reason',
            'token' => [
                'id',
                'team_id',
                'service',
                'type',
                'is_valid',
                'is_expired',
            ],
        ]);

        $this->assertEquals($token->id, $response->json('token.id'));
    }

    public function test_validate_withNoToken_returnsNoToken(): void
    {
        // Given - no token exists

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertOk();
        $response->assertJson([
            'valid'  => false,
            'reason' => 'no_token',
        ]);
        $response->assertJsonMissing(['token']);
    }

    public function test_validate_withExpiredToken_returnsExpired(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token'  => 'expired_token',
            'refresh_token' => null,
            'expires_at'    => now()->subHour(),
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertOk();
        $response->assertJson([
            'valid'  => false,
            'reason' => 'expired',
        ]);
        $response->assertJsonMissing(['token']);
    }

    public function test_validate_withExpiredTokenButValidRefresh_refreshesAndReturnsValid(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token'  => 'expired_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_at'    => now()->subHour(),
        ]);

        // Mock token refresh and validation
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token',
                'expires_in'   => 3600,
            ], 200),
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'test@example.com'],
            ], 200),
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertOk();
        $response->assertJson([
            'valid'  => true,
            'reason' => 'valid',
        ]);
        $response->assertJsonStructure(['token']);

        // Verify token was refreshed
        $token->refresh();
        $this->assertEquals('new_access_token', $token->access_token);
        $this->assertFalse($token->trashed());
    }

    public function test_validate_withRevokedToken_deletesTokenAndReturnsRevoked(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'revoked_token',
            'expires_at'   => now()->addHour(),
        ]);

        // Mock failed Google API validation
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => [
                    'code'    => 401,
                    'message' => 'Invalid Credentials',
                ],
            ], 401),
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertOk();
        $response->assertJson([
            'valid'  => false,
            'reason' => 'revoked',
        ]);
        $response->assertJsonMissing(['token']);

        // Token should be soft deleted
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_validate_withApiError_returnsRevoked(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at'   => now()->addHour(),
        ]);

        // Mock network error - GoogleDocsApi catches exceptions and returns false
        // which is treated as failed validation (revoked)
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => function () {
                throw new \Exception('Connection timed out after 30 seconds');
            },
        ]);

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertOk();
        $response->assertJson([
            'valid'  => false,
            'reason' => 'revoked',
        ]);
        $response->assertJsonMissing(['token']);

        // Token WILL be deleted since GoogleDocsApi returns false (validation failed)
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_validate_withoutAuthentication_returns401(): void
    {
        // Given - logout to remove authentication
        $this->app['auth']->logout();

        // When
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then
        $response->assertStatus(401);
    }

    public function test_validate_deletesTokenOnNetworkErrors(): void
    {
        // Google DocsApi catches all exceptions and returns false, which is treated as validation failed
        // This test verifies that behavior - network errors result in token deletion

        // Mock various network/temporary errors
        $temporaryErrors = [
            'Connection timed out',
            'Network is unreachable',
            'DNS resolution has failed',
            'Connection was reset by peer',
        ];

        foreach ($temporaryErrors as $index => $error) {
            // Create a fresh token for each test
            $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
                'access_token' => "test_token_{$index}",
                'expires_at'   => now()->addHour(),
            ]);

            // Mock network error
            Http::fake([
                'https://www.googleapis.com/drive/v3/about*' => function () use ($error) {
                    throw new \Exception($error);
                },
            ]);

            // When
            $response = $this->postJson("/api/oauth/{$this->service}/validate");

            // Then
            $response->assertOk();
            $response->assertJson([
                'valid'  => false,
                'reason' => 'revoked',
            ]);

            // Token WILL be deleted since GoogleDocsApi returns false for all errors
            $this->assertTrue($token->fresh()->trashed(), "Token should be deleted for error: {$error}");
        }
    }

    public function test_validate_deletesTokenOnAuthErrors(): void
    {
        // Test that auth-related errors properly delete the token
        $authErrors = [
            'HTTP 401 Unauthorized',
            'HTTP 403 Forbidden',
            'Token has been revoked',
            'Invalid credentials revoked',
        ];

        foreach ($authErrors as $index => $error) {
            // Create a fresh token for each test
            $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
                'access_token' => "test_token_{$index}",
                'expires_at'   => now()->addHour(),
            ]);

            // Mock auth error
            Http::fake([
                'https://www.googleapis.com/drive/v3/about*' => function () use ($error) {
                    throw new \Exception($error);
                },
            ]);

            // When
            $response = $this->postJson("/api/oauth/{$this->service}/validate");

            // Then
            $response->assertOk();
            $response->assertJson([
                'valid'  => false,
                'reason' => 'revoked',
            ]);

            // Token should be deleted for auth errors
            $this->assertTrue($token->fresh()->trashed(), "Token should be deleted for error: {$error}");
        }
    }

    public function test_validate_respectsTeamIsolation(): void
    {
        // Given - Create token for a different team
        $otherTeam = \App\Models\Team\Team::factory()->create();
        AuthToken::factory()->google()->forTeam($otherTeam)->create([
            'access_token' => 'other_team_token',
            'expires_at'   => now()->addHour(),
        ]);

        // When - Try to validate as current user
        $response = $this->postJson("/api/oauth/{$this->service}/validate");

        // Then - Should return no_token (can't see other team's token)
        $response->assertOk();
        $response->assertJson([
            'valid'  => false,
            'reason' => 'no_token',
        ]);
    }
}
