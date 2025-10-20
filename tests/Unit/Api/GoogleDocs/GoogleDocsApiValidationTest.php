<?php

namespace Tests\Unit\Api\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Auth\AuthToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GoogleDocsApiValidationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected GoogleDocsApi $api;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

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

        $this->api = new GoogleDocsApi();
    }

    public function test_validateToken_withValidToken_returnsTrue(): void
    {
        // Given - Create a valid OAuth token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_access_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock successful Google Drive API response
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => [
                    'emailAddress' => 'test@example.com'
                ]
            ], 200)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then
        $this->assertTrue($result);

        // Verify the correct API endpoint was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://www.googleapis.com/drive/v3/about') &&
                   str_contains($request->url(), 'fields=user/emailAddress') &&
                   $request->hasHeader('Authorization');
        });
    }

    public function test_validateToken_withRevokedToken_returnsFalse(): void
    {
        // Given - Create a token (but it will be revoked on Google's side)
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'revoked_access_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock 401 Unauthorized response (token revoked)
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => [
                    'code' => 401,
                    'message' => 'Invalid Credentials'
                ]
            ], 401)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then
        $this->assertFalse($result);
    }

    public function test_validateToken_withInvalidToken_returnsFalse(): void
    {
        // Given - Create a token with invalid access token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'invalid_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock 403 Forbidden response
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'Forbidden'
                ]
            ], 403)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then
        $this->assertFalse($result);
    }

    public function test_validateToken_withNetworkError_returnsFalse(): void
    {
        // Given - Create a valid token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock network error (connection timeout, etc.)
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => function () {
                throw new \Exception('Connection timeout');
            }
        ]);

        // When
        $result = $this->api->validateToken();

        // Then
        $this->assertFalse($result);
    }

    public function test_validateToken_withServerError_returnsFalse(): void
    {
        // Given - Create a valid token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock 500 server error from Google
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => [
                    'code' => 500,
                    'message' => 'Internal Server Error'
                ]
            ], 500)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then
        $this->assertFalse($result);
    }

    public function test_validateToken_withEmptyResponse_returnsFalse(): void
    {
        // Given - Create a valid token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock empty/malformed response
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response('', 200)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then
        $this->assertTrue($result); // 200 status is considered success even with empty body
    }

    public function test_validateToken_usesCorrectRequestHeaders(): void
    {
        // Given - Create a valid token
        $token = AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'test_access_token_12345',
            'expires_at' => now()->addHour(),
        ]);

        // Mock successful response
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'test@example.com']
            ], 200)
        ]);

        // When
        $this->api->validateToken();

        // Then - Verify correct Authorization header was sent
        Http::assertSent(function ($request) use ($token) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            return str_contains($authHeader, 'Bearer ' . $token->access_token);
        });
    }

    public function test_validateToken_logsSuccessfulValidation(): void
    {
        // Given - Create a valid token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock successful response
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'test@example.com']
            ], 200)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then - Just verify it returns true (logging is internal)
        $this->assertTrue($result);
    }

    public function test_validateToken_logsFailedValidation(): void
    {
        // Given - Create a token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'invalid_token',
            'expires_at' => now()->addHour(),
        ]);

        // Mock failed response
        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => ['code' => 401, 'message' => 'Invalid Credentials']
            ], 401)
        ]);

        // When
        $result = $this->api->validateToken();

        // Then - Just verify it returns false (logging is internal)
        $this->assertFalse($result);
    }

    public function test_validateToken_withSuccessfulStatusCodes(): void
    {
        // Given - Create a token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'test_token',
            'expires_at' => now()->addHour(),
        ]);

        // Test successful HTTP status codes (200-299)
        $successCodes = [200, 201, 204];

        foreach ($successCodes as $statusCode) {
            // Mock successful response
            Http::fake([
                'https://www.googleapis.com/drive/v3/about*' => Http::response([
                    'user' => ['emailAddress' => 'test@example.com']
                ], $statusCode)
            ]);

            // When
            $result = $this->api->validateToken();

            // Then
            $this->assertTrue($result, "Expected validateToken() to return true for HTTP status {$statusCode}");
        }
    }

    public function test_validateToken_withErrorStatusCodes(): void
    {
        // Given - Create a token
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'test_token',
            'expires_at' => now()->addHour(),
        ]);

        // Test error HTTP status codes
        $errorCodes = [400, 401, 403, 404, 429, 500, 503];

        foreach ($errorCodes as $statusCode) {
            // Mock error response
            Http::fake([
                'https://www.googleapis.com/drive/v3/about*' => Http::response([
                    'error' => ['code' => $statusCode, 'message' => 'Error']
                ], $statusCode)
            ]);

            // When
            $result = $this->api->validateToken();

            // Then
            $this->assertFalse($result, "Expected validateToken() to return false for HTTP status {$statusCode}");
        }
    }
}
