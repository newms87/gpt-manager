<?php

namespace Tests\Unit\Api\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Auth\AuthToken;
use App\Services\Auth\OAuthService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Newms87\Danx\Exceptions\ApiException;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class GoogleDocsApiTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected GoogleDocsApi $api;
    protected $team;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->team = $this->user->currentTeam;
        $this->api = new GoogleDocsApi();

        // Mock Google OAuth configuration for token refresh
        Config::set('auth.oauth.google', [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uri' => 'http://localhost/api/oauth/callback',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'revoke_url' => 'https://oauth2.googleapis.com/revoke',
            'scopes' => [
                'https://www.googleapis.com/auth/documents',
                'https://www.googleapis.com/auth/drive'
            ],
            'access_type' => 'offline'
        ]);
    }

    public function test_initializeAuthentication_withValidOAuthToken_succeeds(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_access_token'
        ]);

        // When
        $headers = $this->api->getRequestHeaders();

        // Then
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer oauth_access_token', $headers['Authorization']);
    }

    public function test_initializeAuthentication_withoutOAuthToken_throwsException(): void
    {
        // Given - no OAuth token exists

        // Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('No valid OAuth token found');

        // When
        $this->api->getRequestHeaders();
    }

    public function test_initializeAuthentication_withExpiredOAuthToken_throwsException(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->expired()->create();

        // Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('No valid OAuth token found');

        // When
        $this->api->getRequestHeaders();
    }

    public function test_hasOAuthToken_withValidToken_returnsTrue(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create();

        // When
        $hasToken = $this->api->hasOAuthToken();

        // Then
        $this->assertTrue($hasToken);
    }

    public function test_hasOAuthToken_withExpiredToken_returnsFalse(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->expired()->create();

        // When
        $hasToken = $this->api->hasOAuthToken();

        // Then
        $this->assertFalse($hasToken);
    }

    public function test_hasOAuthToken_withoutToken_returnsFalse(): void
    {
        // When
        $hasToken = $this->api->hasOAuthToken();

        // Then
        $this->assertFalse($hasToken);
    }

    public function test_reinitializeAuth_resetsAuthenticationState(): void
    {
        // Given
        $token = AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'initial_token'
        ]);
        
        // Initialize once
        $headers1 = $this->api->getRequestHeaders();
        $this->assertEquals('Bearer initial_token', $headers1['Authorization']);
        
        // Update token
        $token->update(['access_token' => 'updated_token']);

        // When
        $this->api->reinitializeAuth();
        $headers2 = $this->api->getRequestHeaders();

        // Then
        $this->assertEquals('Bearer updated_token', $headers2['Authorization']);
    }

    public function test_getRequestHeaders_withOAuth_includesOAuthToken(): void
    {
        // Given
        $accessToken = 'oauth_access_token';
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => $accessToken
        ]);

        // When
        $headers = $this->api->getRequestHeaders();

        // Then
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals("Bearer $accessToken", $headers['Authorization']);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('application/json', $headers['Accept']);
    }

    public function test_initializeOAuth_withTokenNearExpiry_attemptsRefresh(): void
    {
        // Given
        $originalToken = AuthToken::factory()->google()->forTeam($this->team)->expiresSoon()->create([
            'access_token' => 'old_token',
            'refresh_token' => 'refresh_token'
        ]);
        
        // Mock refresh response
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token',
                'expires_in' => 3600,
            ], 200)
        ]);

        // When
        $headers = $this->api->getRequestHeaders();

        // Then - should have tried to refresh and used new token
        $this->assertEquals('Bearer new_access_token', $headers['Authorization']);
    }

    public function test_initializeOAuth_withRefreshFailure_usesExpiredToken(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->expiresSoon()->create([
            'access_token' => 'old_token',
            'refresh_token' => 'refresh_token'
        ]);
        
        // Mock refresh failure
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant'
            ], 400)
        ]);

        // When
        $headers = $this->api->getRequestHeaders();

        // Then - should still use the old token even though refresh failed
        $this->assertEquals('Bearer old_token', $headers['Authorization']);
    }

    public function test_readDocument_withOAuthAuthentication_makesApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token'
        ]);
        
        $documentId = 'test_document_id';
        $documentContent = [
            'title' => 'Test Document',
            'body' => [
                'content' => [
                    [
                        'paragraph' => [
                            'elements' => [
                                ['textRun' => ['content' => 'Test content']]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode($documentContent))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // When
        $result = $this->api->readDocument($documentId);

        // Then
        $this->assertEquals($documentId, $result['document_id']);
        $this->assertEquals('Test Document', $result['title']);
        $this->assertEquals('Test content', $result['content']);
    }

    public function test_parseTemplateVariables_extractsVariables(): void
    {
        // Given
        $content = 'Hello {{name}}, your order {{order_id}} is ready. Total: {{total}}';

        // When
        $variables = $this->api->parseTemplateVariables($content);

        // Then
        $this->assertCount(3, $variables);
        $this->assertContains('name', $variables);
        $this->assertContains('order_id', $variables);
        $this->assertContains('total', $variables);
    }

    public function test_replaceVariables_replacesAllVariables(): void
    {
        // Given
        $content = 'Hello {{name}}, your order {{order_id}} is ready.';
        $mappings = [
            'name' => 'John Doe',
            'order_id' => '12345'
        ];

        // When
        $result = $this->api->replaceVariables($content, $mappings);

        // Then
        $this->assertEquals('Hello John Doe, your order 12345 is ready.', $result);
    }

    public function test_createDocument_withOAuth_createsDocument(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token'
        ]);
        
        Http::fake([
            'https://www.googleapis.com/drive/v3/files' => Http::response([
                'id' => 'new_doc_id'
            ], 200),
            'https://docs.googleapis.com/v1/documents/new_doc_id:batchUpdate' => Http::response([], 200)
        ]);

        // When
        $result = $this->api->createDocument('Test Title', 'Test Content');

        // Then
        $this->assertEquals('new_doc_id', $result['document_id']);
        $this->assertEquals('Test Title', $result['title']);
        $this->assertStringContainsString('docs.google.com/document/d/new_doc_id', $result['url']);
    }

    public function test_createDocumentFromTemplate_copiesAndReplacesVariables(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token'
        ]);
        
        $templateId = 'template_id';
        $variableMappings = [
            'name' => 'John Doe',
            'date' => '2024-01-01'
        ];
        
        // Mock Laravel HTTP facade for Drive API calls
        Http::fake([
            "https://www.googleapis.com/drive/v3/files/$templateId/copy" => Http::response([
                'id' => 'copied_doc_id'
            ], 200)
        ]);
        
        // Create mock Guzzle client for Docs API calls (batch update)
        $mock = new MockHandler([
            new Response(200, [], json_encode([]))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // When
        $result = $this->api->createDocumentFromTemplate($templateId, $variableMappings, 'New Document');

        // Then
        $this->assertEquals('copied_doc_id', $result['document_id']);
        $this->assertEquals('New Document', $result['title']);
    }

    public function test_extractTemplateVariables_fromGoogleDoc(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token'
        ]);
        
        $templateId = 'template_id';
        $documentContent = [
            'title' => 'Template',
            'body' => [
                'content' => [
                    [
                        'paragraph' => [
                            'elements' => [
                                ['textRun' => ['content' => 'Hello {{customer_name}}, your {{product}} is ready.']]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode($documentContent))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // When
        $variables = $this->api->extractTemplateVariables($templateId);

        // Then
        $this->assertCount(2, $variables);
        $this->assertContains('customer_name', $variables);
        $this->assertContains('product', $variables);
    }
}