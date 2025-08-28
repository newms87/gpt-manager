<?php

namespace Tests\Unit\Services\Auth;

use App\Exceptions\Auth\NoTokenFoundException;
use App\Exceptions\Auth\TokenExpiredException;
use App\Exceptions\Auth\TokenRevokedException;
use App\Models\Auth\AuthToken;
use App\Models\Team\Team;
use App\Services\Auth\OAuthService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class OAuthServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected OAuthService $service;
    protected string $testService = 'google';

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(OAuthService::class);

        // Mock Google OAuth configuration
        Config::set('auth.oauth.google', [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uri' => 'http://localhost/api/oauth/google/callback',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'revoke_url' => 'https://oauth2.googleapis.com/revoke',
            'scopes' => [
                'https://www.googleapis.com/auth/documents',
                'https://www.googleapis.com/auth/drive',
            ],
            'access_type' => 'offline',
            'approval_prompt' => 'force'
        ]);
    }

    public function test_isConfigured_withValidConfig_returnsTrue(): void
    {
        // When & Then
        $this->assertTrue($this->service->isConfigured($this->testService));
    }

    public function test_isConfigured_withMissingClientId_returnsFalse(): void
    {
        // Given
        Config::set('auth.oauth.google.client_id', '');

        // When & Then
        $this->assertFalse($this->service->isConfigured($this->testService));
    }

    public function test_isConfigured_withMissingClientSecret_returnsFalse(): void
    {
        // Given
        Config::set('auth.oauth.google.client_secret', '');

        // When & Then
        $this->assertFalse($this->service->isConfigured($this->testService));
    }

    public function test_hasValidToken_withValidToken_returnsTrue(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour()
        ]);

        // When & Then
        $this->assertTrue($this->service->hasValidToken($this->testService, $this->user->currentTeam));
    }

    public function test_hasValidToken_withExpiredToken_returnsFalse(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'expires_at' => now()->subHour()
        ]);

        // When & Then
        $this->assertFalse($this->service->hasValidToken($this->testService, $this->user->currentTeam));
    }

    public function test_hasValidToken_withNoToken_returnsFalse(): void
    {
        // When & Then
        $this->assertFalse($this->service->hasValidToken($this->testService, $this->user->currentTeam));
    }

    public function test_hasValidTokenWithScopes_withValidTokenAndCorrectScopes_returnsTrue(): void
    {
        // Given
        $requiredScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive'];
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
            'scopes' => $requiredScopes
        ]);

        // When & Then
        $this->assertTrue($this->service->hasValidTokenWithScopes($this->testService, $requiredScopes, $this->user->currentTeam));
    }

    public function test_hasValidTokenWithScopes_withValidTokenButInsufficientScopes_returnsFalse(): void
    {
        // Given
        $tokenScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive.file'];
        $requiredScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive'];
        
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
            'scopes' => $tokenScopes
        ]);

        // When & Then
        $this->assertFalse($this->service->hasValidTokenWithScopes($this->testService, $requiredScopes, $this->user->currentTeam));
    }

    public function test_hasValidTokenWithScopes_withValidTokenAndExtraScopes_returnsTrue(): void
    {
        // Given
        $tokenScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/userinfo.email'];
        $requiredScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive'];
        
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
            'scopes' => $tokenScopes
        ]);

        // When & Then
        $this->assertTrue($this->service->hasValidTokenWithScopes($this->testService, $requiredScopes, $this->user->currentTeam));
    }

    public function test_hasValidTokenWithScopes_withExpiredToken_returnsFalse(): void
    {
        // Given
        $requiredScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive'];
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->subHour(),
            'scopes' => $requiredScopes
        ]);

        // When & Then
        $this->assertFalse($this->service->hasValidTokenWithScopes($this->testService, $requiredScopes, $this->user->currentTeam));
    }

    public function test_hasValidTokenWithScopes_withNoToken_returnsFalse(): void
    {
        // Given
        $requiredScopes = ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive'];

        // When & Then
        $this->assertFalse($this->service->hasValidTokenWithScopes($this->testService, $requiredScopes, $this->user->currentTeam));
    }

    public function test_getToken_withValidToken_returnsToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();

        // When
        $result = $this->service->getToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_getToken_withExpiringToken_refreshesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->expiresSoon()->create();
        $originalAccessToken = $token->access_token;

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed_access_token',
                'expires_in' => 3600,
            ], 200)
        ]);

        // When
        $result = $this->service->getToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertNotEquals($originalAccessToken, $result->access_token);
        $this->assertEquals('refreshed_access_token', $result->access_token);
    }

    public function test_getToken_withNoToken_returnsNull(): void
    {
        // When
        $result = $this->service->getToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertNull($result);
    }

    public function test_getAuthorizationUrl_withValidConfig_returnsUrl(): void
    {
        // When
        $url = $this->service->getAuthorizationUrl($this->testService);

        // Then
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('approval_prompt=force', $url);
    }

    public function test_getAuthorizationUrl_withState_includesState(): void
    {
        // Given
        $state = 'test_state_value';

        // When
        $url = $this->service->getAuthorizationUrl($this->testService, $state);

        // Then
        $this->assertStringContainsString("state=$state", $url);
    }

    public function test_getAuthorizationUrl_withTeam_includesTeamInState(): void
    {
        // When
        $url = $this->service->getAuthorizationUrl($this->testService, null, $this->user->currentTeam);

        // Then
        $this->assertStringContainsString('state=', $url);
        
        // Extract state from URL and verify it contains team data
        preg_match('/state=([^&]+)/', $url, $matches);
        if (empty($matches)) {
            $this->fail('State parameter not found in URL: ' . $url);
        }
        $stateData = json_decode(base64_decode(urldecode($matches[1])), true);
        
        if (!$stateData) {
            $this->fail('Failed to decode state data from: ' . $matches[1]);
        }
        
        $this->assertEquals($this->testService, $stateData['service']);
        $this->assertEquals($this->user->currentTeam->id, $stateData['team_id']);
        $this->assertIsNumeric($stateData['timestamp']);
    }

    public function test_getAuthorizationUrl_withoutConfiguration_throwsValidationError(): void
    {
        // Given
        Config::set('auth.oauth.google.client_id', '');

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('OAuth is not properly configured for service: google');
        $this->service->getAuthorizationUrl($this->testService);
    }

    public function test_exchangeCodeForToken_withValidResponse_returnsTokenData(): void
    {
        // Given
        $code = 'test_authorization_code';
        $tokenData = [
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/documents https://www.googleapis.com/auth/drive'
        ];

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response($tokenData, 200)
        ]);

        // When
        $result = $this->service->exchangeCodeForToken($this->testService, $code);

        // Then
        $this->assertEquals($tokenData, $result);
        Http::assertSent(function ($request) use ($code) {
            return $request->url() === 'https://oauth2.googleapis.com/token' &&
                   $request['code'] === $code &&
                   $request['client_id'] === 'test_client_id' &&
                   $request['client_secret'] === 'test_client_secret' &&
                   $request['grant_type'] === 'authorization_code';
        });
    }

    public function test_exchangeCodeForToken_withErrorResponse_throwsValidationError(): void
    {
        // Given
        $code = 'invalid_code';
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid authorization code'
            ], 400)
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Failed to exchange authorization code: Invalid authorization code');
        $this->service->exchangeCodeForToken($this->testService, $code);
    }

    public function test_exchangeCodeForToken_withMissingAccessToken_throwsValidationError(): void
    {
        // Given
        $code = 'test_code';
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'refresh_token' => 'test_refresh_token',
                // Missing access_token
            ], 200)
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid token response - missing access_token');
        $this->service->exchangeCodeForToken($this->testService, $code);
    }

    public function test_exchangeCodeForToken_withoutConfiguration_throwsValidationError(): void
    {
        // Given
        Config::set('auth.oauth.google.client_id', '');
        $code = 'test_code';

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('OAuth is not properly configured for service: google');
        $this->service->exchangeCodeForToken($this->testService, $code);
    }

    public function test_storeToken_withValidData_storesToken(): void
    {
        // Given
        $tokenData = [
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/documents https://www.googleapis.com/auth/drive'
        ];

        // When
        $token = $this->service->storeToken($this->testService, $tokenData, $this->user->currentTeam);

        // Then
        $this->assertInstanceOf(AuthToken::class, $token);
        $this->assertEquals($this->user->currentTeam->id, $token->team_id);
        $this->assertEquals($this->testService, $token->service);
        $this->assertEquals(AuthToken::TYPE_OAUTH, $token->type);
        $this->assertEquals($tokenData['access_token'], $token->access_token);
        $this->assertEquals($tokenData['refresh_token'], $token->refresh_token);
        $this->assertTrue($token->expires_at->greaterThan(now()));
        
        $this->assertDatabaseHas('auth_tokens', [
            'team_id' => $this->user->currentTeam->id,
            'service' => $this->testService,
            'type' => AuthToken::TYPE_OAUTH,
            'access_token' => $tokenData['access_token']
        ]);
    }

    public function test_storeToken_replacesExistingToken(): void
    {
        // Given
        $existingToken = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        $tokenData = [
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 3600,
        ];

        // When
        $newToken = $this->service->storeToken($this->testService, $tokenData, $this->user->currentTeam);

        // Then
        $this->assertInstanceOf(AuthToken::class, $newToken);
        $this->assertEquals('new_access_token', $newToken->access_token);
        
        // Verify old token is deleted (hard deleted since store replaces)
        $this->assertDatabaseMissing('auth_tokens', [
            'id' => $existingToken->id
        ]);
    }

    public function test_storeToken_withMetadata_storesMetadata(): void
    {
        // Given
        $tokenData = [
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'expires_in' => 3600,
        ];
        $metadata = ['custom_field' => 'custom_value'];

        // When
        $token = $this->service->storeToken($this->testService, $tokenData, $this->user->currentTeam, $metadata);

        // Then
        $this->assertEquals($metadata, $token->metadata);
        $this->assertEquals('custom_value', $token->getMetadata('custom_field'));
    }

    public function test_refreshToken_withValidToken_updatesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->expiresSoon()->create();
        $originalAccessToken = $token->access_token;
        $originalExpiresAt = $token->expires_at;
        $refreshResponse = [
            'access_token' => 'new_access_token',
            'expires_in' => 3600,
        ];

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response($refreshResponse, 200)
        ]);

        // When
        $result = $this->service->refreshToken($this->testService, $token);

        // Then
        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertNotEquals($originalAccessToken, $result->access_token);
        $this->assertEquals('new_access_token', $result->access_token);
        $this->assertTrue($result->expires_at->greaterThan($originalExpiresAt));
        
        Http::assertSent(function ($request) use ($token) {
            return $request->url() === 'https://oauth2.googleapis.com/token' &&
                   $request['grant_type'] === 'refresh_token' &&
                   $request['refresh_token'] === $token->refresh_token &&
                   $request['client_id'] === 'test_client_id' &&
                   $request['client_secret'] === 'test_client_secret';
        });
    }

    public function test_refreshToken_withInvalidGrant_deletesTokenAndThrowsError(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been revoked'
            ], 400)
        ]);

        // When & Then
        $this->expectException(TokenRevokedException::class);
        $this->expectExceptionMessage('OAuth token for google');
        
        $this->service->refreshToken($this->testService, $token);

        // Verify token was soft deleted
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_refreshToken_withWrongTeam_throwsValidationError(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $token = AuthToken::factory()->google()->forTeam($otherTeam)->create();

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this OAuth token');
        $this->service->refreshToken($this->testService, $token);
    }

    public function test_refreshToken_withServiceMismatch_throwsValidationError(): void
    {
        // Given
        $token = AuthToken::factory()->stripe()->forTeam($this->user->currentTeam)->create();

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Token service mismatch');
        $this->service->refreshToken($this->testService, $token);
    }

    public function test_refreshToken_withoutRefreshToken_throwsValidationError(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'refresh_token' => null
        ]);

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No refresh token available');
        $this->service->refreshToken($this->testService, $token);
    }

    public function test_revokeToken_withExistingToken_revokesAndDeletesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response(null, 200)
        ]);

        // When
        $result = $this->service->revokeToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertTrue($result);
        // Should be soft deleted, not hard deleted
        $this->assertTrue($token->fresh()->trashed());
        $this->assertDatabaseHas('auth_tokens', [
            'id' => $token->id
        ]);
        Http::assertSent(function ($request) use ($token) {
            return $request->url() === 'https://oauth2.googleapis.com/revoke' &&
                   $request['token'] === $token->refresh_token;
        });
    }

    public function test_revokeToken_withNoToken_returnsFalse(): void
    {
        // When
        $result = $this->service->revokeToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertFalse($result);
    }

    public function test_revokeSpecificToken_withValidToken_revokesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response(null, 200)
        ]);

        // When
        $result = $this->service->revokeSpecificToken($this->testService, $token);

        // Then
        $this->assertTrue($result);
        // Should be soft deleted, not hard deleted
        $this->assertTrue($token->fresh()->trashed());
        $this->assertDatabaseHas('auth_tokens', [
            'id' => $token->id
        ]);
    }

    public function test_revokeSpecificToken_withWrongTeam_throwsValidationError(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $token = AuthToken::factory()->google()->forTeam($otherTeam)->create();

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this OAuth token');
        $this->service->revokeSpecificToken($this->testService, $token);
    }

    public function test_revokeToken_withServiceWithoutRevokeUrl_stillDeletesLocally(): void
    {
        // Given - Configure service without revoke_url
        Config::set('auth.oauth.test_service', [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            // No revoke_url
        ]);
        
        $token = AuthToken::factory()->forService('test_service')->forTeam($this->user->currentTeam)->create();

        // When
        $result = $this->service->revokeSpecificToken('test_service', $token);

        // Then
        $this->assertTrue($result);
        // Should be soft deleted, not hard deleted
        $this->assertTrue($token->fresh()->trashed());
        $this->assertDatabaseHas('auth_tokens', [
            'id' => $token->id
        ]);
        Http::assertNothingSent();
    }

    public function test_getServiceConfig_withMissingService_throwsValidationError(): void
    {
        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('OAuth configuration not found for service: nonexistent');
        $this->service->getAuthorizationUrl('nonexistent');
    }

    public function test_validateTokenData_withMissingAccessToken_throwsValidationError(): void
    {
        // Given
        $invalidTokenData = [
            'refresh_token' => 'test_refresh_token'
            // Missing access_token
        ];

        // When & Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Missing access_token in OAuth response');
        $this->service->storeToken($this->testService, $invalidTokenData, $this->user->currentTeam);
    }

    // Tests for new getValidToken method and specific exceptions

    public function test_getValidToken_withNoToken_throwsNoTokenFoundException(): void
    {
        // Given - no token exists

        // When & Then
        $this->expectException(NoTokenFoundException::class);
        $this->expectExceptionMessage('No OAuth token found for google');
        
        $exception = null;
        try {
            $this->service->getValidToken($this->testService, $this->user->currentTeam);
        } catch (NoTokenFoundException $e) {
            $exception = $e;
            throw $e;
        }

        // Verify exception context
        $this->assertEquals($this->testService, $exception->getService());
        $this->assertEquals($this->user->currentTeam->id, $exception->getTeamId());
        $this->assertEquals('oauth_authorization', $exception->getActionRequired());
    }

    public function test_getValidToken_withValidToken_returnsToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);

        // When
        $result = $this->service->getValidToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertEquals($token->id, $result->id);
        $this->assertEquals('valid_token', $result->access_token);
    }

    public function test_getValidToken_withExpiredTokenNoRefresh_throwsTokenExpiredException(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'expired_token',
            'refresh_token' => null,
            'expires_at' => now()->subHour(),
        ]);

        // When & Then
        $this->expectException(TokenExpiredException::class);
        $this->expectExceptionMessage('OAuth token for google');
        
        $exception = null;
        try {
            $this->service->getValidToken($this->testService, $this->user->currentTeam);
        } catch (TokenExpiredException $e) {
            $exception = $e;
            throw $e;
        }
        
        // Verify exception context
        $this->assertEquals($this->testService, $exception->getService());
        $this->assertEquals($this->user->currentTeam->id, $exception->getTeamId());
        $this->assertNotNull($exception->getExpiresAt());
        $this->assertEquals('oauth_authorization', $exception->getActionRequired());
        
        // Token should be soft deleted
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_getValidToken_withExpiredTokenValidRefresh_refreshesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'expired_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_at' => now()->subHour(),
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/documents'
            ], 200)
        ]);

        // When
        $result = $this->service->getValidToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertEquals($token->id, $result->id);
        $this->assertEquals('new_access_token', $result->access_token);
        $this->assertTrue($result->expires_at->isAfter(now()));
        $this->assertFalse($result->trashed()); // Should not be soft deleted
    }

    public function test_getValidToken_withRefreshFailure_throwsTokenRevokedException(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'expired_token',
            'refresh_token' => 'invalid_refresh_token',
            'expires_at' => now()->subHour(),
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been revoked'
            ], 400)
        ]);

        // When & Then
        $this->expectException(TokenRevokedException::class);
        $this->expectExceptionMessage('OAuth token for google');
        
        $exception = null;
        try {
            $this->service->getValidToken($this->testService, $this->user->currentTeam);
        } catch (TokenRevokedException $e) {
            $exception = $e;
            throw $e;
        }
        
        // Verify exception context
        $this->assertEquals($this->testService, $exception->getService());
        $this->assertEquals($this->user->currentTeam->id, $exception->getTeamId());
        $this->assertEquals('Token has been revoked', $exception->getRevokeReason());
        $this->assertEquals('oauth_authorization', $exception->getActionRequired());
        
        // Token should be soft deleted
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_refreshToken_withRevokedToken_throwsTokenRevokedException(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'old_token',
            'refresh_token' => 'revoked_refresh_token',
            'expires_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been revoked'
            ], 400)
        ]);

        // When & Then
        $this->expectException(TokenRevokedException::class);
        $this->expectExceptionMessage('OAuth token for google');
        
        $exception = null;
        try {
            $this->service->refreshToken($this->testService, $token);
        } catch (TokenRevokedException $e) {
            $exception = $e;
            throw $e;
        }
        
        // Verify exception context and token is soft deleted
        $this->assertEquals($this->testService, $exception->getService());
        $this->assertEquals($token->team_id, $exception->getTeamId());
        $this->assertEquals('Token has been revoked', $exception->getRevokeReason());
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_revokeToken_softDeletesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response(null, 200)
        ]);

        // When
        $result = $this->service->revokeToken($this->testService, $this->user->currentTeam);

        // Then
        $this->assertTrue($result);
        // Should be soft deleted, not hard deleted
        $this->assertTrue($token->fresh()->trashed());
        $this->assertDatabaseHas('auth_tokens', [
            'id' => $token->id
        ]);
    }

    // Tests for AuthToken model convenience methods

    public function test_authToken_canBeRefreshed_withRefreshToken_returnsTrue(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'refresh_token' => 'valid_refresh_token',
        ]);

        // When & Then
        $this->assertTrue($token->canBeRefreshed());
    }

    public function test_authToken_canBeRefreshed_withoutRefreshToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'refresh_token' => null,
        ]);

        // When & Then
        $this->assertFalse($token->canBeRefreshed());
    }

    public function test_authToken_canBeRefreshed_withApiKey_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'service' => 'stripe',
            'type' => AuthToken::TYPE_API_KEY,
            'refresh_token' => 'should_not_matter',
        ]);

        // When & Then
        $this->assertFalse($token->canBeRefreshed());
    }

    public function test_authToken_isLikelyRevoked_withExpiredTokenAndNoRefresh_returnsTrue(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'expires_at' => now()->subHour(),
            'refresh_token' => null,
        ]);

        // When & Then
        $this->assertTrue($token->isLikelyRevoked());
    }

    public function test_authToken_isLikelyRevoked_withExpiredTokenButHasRefresh_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'expires_at' => now()->subHour(),
            'refresh_token' => 'valid_refresh_token',
        ]);

        // When & Then
        $this->assertFalse($token->isLikelyRevoked());
    }

    public function test_authToken_markAsInvalid_softDeletesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();

        // When
        $result = $token->markAsInvalid();

        // Then
        $this->assertTrue($result);
        $this->assertTrue($token->fresh()->trashed());
    }
}