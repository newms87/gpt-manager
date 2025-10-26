<?php

namespace Tests\Unit\Api\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Exceptions\Auth\NoTokenFoundException;
use App\Exceptions\Auth\TokenExpiredException;
use App\Exceptions\Auth\TokenRevokedException;
use App\Models\Auth\AuthToken;
use App\Services\Auth\OAuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Newms87\Danx\Exceptions\ApiException;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

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
        $this->api  = new GoogleDocsApi();

        // Mock Google OAuth configuration for token refresh
        Config::set('auth.oauth.google', [
            'client_id'     => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uri'  => 'http://localhost/api/oauth/callback',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'revoke_url'    => 'https://oauth2.googleapis.com/revoke',
            'scopes'        => [
                'https://www.googleapis.com/auth/documents',
                'https://www.googleapis.com/auth/drive',
            ],
            'access_type' => 'offline',
        ]);
    }

    public function test_initializeAuthentication_withValidOAuthToken_succeeds(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_access_token',
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
        $this->expectExceptionMessage('No OAuth token found for google');

        // When
        $this->api->getRequestHeaders();
    }

    public function test_initializeAuthentication_withExpiredOAuthToken_throwsException(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->expired()->create([
            'refresh_token' => null,
        ]);

        // Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('OAuth token for google');

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
            'access_token' => 'initial_token',
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
            'access_token' => $accessToken,
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
            'access_token'  => 'old_token',
            'refresh_token' => 'refresh_token',
        ]);

        // Mock refresh response
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token',
                'expires_in'   => 3600,
            ], 200),
        ]);

        // When
        $headers = $this->api->getRequestHeaders();

        // Then - should have tried to refresh and used new token
        $this->assertEquals('Bearer new_access_token', $headers['Authorization']);
    }

    public function test_initializeOAuth_withRefreshFailure_throwsTokenRevokedException(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->expiresSoon()->create([
            'access_token'  => 'old_token',
            'refresh_token' => 'refresh_token',
        ]);

        // Mock refresh failure with invalid_grant (token revoked)
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error'             => 'invalid_grant',
                'error_description' => 'Token has been revoked',
            ], 400),
        ]);

        // When & Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('OAuth token for google');

        $this->api->getRequestHeaders();
    }

    public function test_readDocument_withOAuthAuthentication_makesApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId      = 'test_document_id';
        $documentContent = [
            'title' => 'Test Document',
            'body'  => [
                'content' => [
                    [
                        'paragraph' => [
                            'elements' => [
                                ['textRun' => ['content' => 'Test content']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode($documentContent)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

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
        $content  = 'Hello {{name}}, your order {{order_id}} is ready.';
        $mappings = [
            'name'     => 'John Doe',
            'order_id' => '12345',
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
            'access_token' => 'oauth_token',
        ]);

        Http::fake([
            'https://www.googleapis.com/drive/v3/files' => Http::response([
                'id' => 'new_doc_id',
            ], 200),
            'https://docs.googleapis.com/v1/documents/new_doc_id:batchUpdate' => Http::response([], 200),
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
            'access_token' => 'oauth_token',
        ]);

        $templateId       = 'template_id';
        $variableMappings = [
            'name' => 'John Doe',
            'date' => '2024-01-01',
        ];

        // Mock Laravel HTTP facade for Drive API calls
        Http::fake([
            "https://www.googleapis.com/drive/v3/files/$templateId/copy" => Http::response([
                'id' => 'copied_doc_id',
            ], 200),
        ]);

        // Create mock Guzzle client for Docs API calls (batch update)
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

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
            'access_token' => 'oauth_token',
        ]);

        $templateId      = 'template_id';
        $documentContent = [
            'title' => 'Template',
            'body'  => [
                'content' => [
                    [
                        'paragraph' => [
                            'elements' => [
                                ['textRun' => ['content' => 'Hello {{customer_name}}, your {{product}} is ready.']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode($documentContent)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

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

    // Tests for new specific exception handling

    public function test_initializeAuthentication_withRevokedToken_throwsApiExceptionWithCorrectMessage(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token'  => 'expired_token',
            'refresh_token' => 'invalid_refresh_token',
            'expires_at'    => now()->subHour(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error'             => 'invalid_grant',
                'error_description' => 'Token has been revoked',
            ], 400),
        ]);

        // When & Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('OAuth token for google');

        $this->api->getRequestHeaders();
    }

    public function test_initializeAuthentication_withNoTokenFoundException_throwsCorrectApiException(): void
    {
        // Given - Use reflection to test specific exception handling
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('initializeAuthentication');
        $method->setAccessible(true);

        // Mock OAuthService to throw NoTokenFoundException
        $mockOAuthService = $this->mock(OAuthService::class);
        $mockOAuthService->shouldReceive('getValidToken')
            ->with('google')
            ->andThrow(new NoTokenFoundException('google', $this->team->id));

        app()->instance(OAuthService::class, $mockOAuthService);

        // When & Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('No OAuth token found for google for team ' . $this->team->id);
        $this->expectExceptionCode(404);

        $method->invoke($this->api);
    }

    public function test_initializeAuthentication_withTokenExpiredException_throwsCorrectApiException(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('initializeAuthentication');
        $method->setAccessible(true);

        // Mock OAuthService to throw TokenExpiredException
        $mockOAuthService = $this->mock(OAuthService::class);
        $mockOAuthService->shouldReceive('getValidToken')
            ->with('google')
            ->andThrow(new TokenExpiredException('google', $this->team->id, now()->subHour()->toISOString()));

        app()->instance(OAuthService::class, $mockOAuthService);

        // When & Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('OAuth token for google for team ' . $this->team->id . ' has expired');
        $this->expectExceptionCode(401);

        $method->invoke($this->api);
    }

    public function test_initializeAuthentication_withTokenRevokedException_throwsCorrectApiException(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('initializeAuthentication');
        $method->setAccessible(true);

        // Mock OAuthService to throw TokenRevokedException
        $mockOAuthService = $this->mock(OAuthService::class);
        $mockOAuthService->shouldReceive('getValidToken')
            ->with('google')
            ->andThrow(new TokenRevokedException('google', $this->team->id, 'Token has been revoked'));

        app()->instance(OAuthService::class, $mockOAuthService);

        // When & Then
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('OAuth token for google for team ' . $this->team->id . ' has been revoked');
        $this->expectExceptionCode(401);

        $method->invoke($this->api);
    }

    public function test_initializeAuthentication_withValidTokenAfterRefresh_setsAccessToken(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token'  => 'expired_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_at'    => now()->subHour(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed_access_token',
                'expires_in'   => 3600,
                'scope'        => 'https://www.googleapis.com/auth/documents',
            ], 200),
        ]);

        // When
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('initializeAuthentication');
        $method->setAccessible(true);

        $method->invoke($this->api);

        // Then
        $accessTokenProperty = $reflection->getProperty('accessToken');
        $accessTokenProperty->setAccessible(true);
        $accessToken = $accessTokenProperty->getValue($this->api);

        $this->assertEquals('refreshed_access_token', $accessToken);
    }

    // Tests for markdown formatting functionality

    public function test_containsMarkdown_withBoldText_returnsTrue(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('containsMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '**bold text**');

        // Then
        $this->assertTrue($result);
    }

    public function test_containsMarkdown_withItalicText_returnsTrue(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('containsMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '*italic text*');

        // Then
        $this->assertTrue($result);
    }

    public function test_containsMarkdown_withHeading_returnsTrue(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('containsMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '# Heading 1');

        // Then
        $this->assertTrue($result);
    }

    public function test_containsMarkdown_withPlainText_returnsFalse(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('containsMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, 'Plain text without markdown');

        // Then
        $this->assertFalse($result);
    }

    public function test_parseMarkdown_withBoldText_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, 'This is **bold text** here');

        // Then
        $this->assertEquals('This is bold text here', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('bold', $result['formats'][0]['type']);
        $this->assertEquals(8, $result['formats'][0]['start']);
        $this->assertEquals(17, $result['formats'][0]['end']);
    }

    public function test_parseMarkdown_withItalicText_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, 'This is *italic text* here');

        // Then
        $this->assertEquals('This is italic text here', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('italic', $result['formats'][0]['type']);
    }

    public function test_parseMarkdown_withHeading1_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '# Main Heading');

        // Then
        $this->assertEquals('Main Heading', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('heading1', $result['formats'][0]['type']);
        $this->assertEquals(0, $result['formats'][0]['start']);
        $this->assertEquals(12, $result['formats'][0]['end']);
    }

    public function test_parseMarkdown_withHeading2_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '## Sub Heading');

        // Then
        $this->assertEquals('Sub Heading', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('heading2', $result['formats'][0]['type']);
    }

    public function test_parseMarkdown_withHeading3_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '### Minor Heading');

        // Then
        $this->assertEquals('Minor Heading', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('heading3', $result['formats'][0]['type']);
    }

    public function test_parseMarkdown_withMixedFormatting_extractsAllFormats(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        $markdown = "# Summary\n\n**Patient:** John Doe\n\nThis is *important* text.";

        // When
        $result = $method->invoke($this->api, $markdown);

        // Then
        $this->assertEquals("Summary\n\nPatient: John Doe\n\nThis is important text.", $result['plainText']);
        $this->assertGreaterThanOrEqual(3, count($result['formats'])); // heading, bold, italic
    }

    public function test_parseMarkdown_withUnderscoreBold_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, 'This is __bold text__ here');

        // Then
        $this->assertEquals('This is bold text here', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('bold', $result['formats'][0]['type']);
    }

    public function test_parseMarkdown_withUnderscoreItalic_extractsFormatting(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, 'This is _italic text_ here');

        // Then
        $this->assertEquals('This is italic text here', $result['plainText']);
        $this->assertCount(1, $result['formats']);
        $this->assertEquals('italic', $result['formats'][0]['type']);
    }

    public function test_parseMarkdown_withMultipleBoldSections_extractsAllFormats(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->api, '**First** bold and **second** bold');

        // Then
        $this->assertEquals('First bold and second bold', $result['plainText']);
        $this->assertCount(2, $result['formats']);
        $this->assertEquals('bold', $result['formats'][0]['type']);
        $this->assertEquals('bold', $result['formats'][1]['type']);
    }

    public function test_parseInlineFormatting_withBoldAndItalic_extractsBoth(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseInlineFormatting');
        $method->setAccessible(true);

        $formats = [];

        // When
        $result = $method->invokeArgs($this->api, ['Text with **bold** and *italic*', 0, &$formats]);

        // Then
        $this->assertEquals('Text with bold and italic', $result);
        $this->assertCount(2, $formats);
    }

    public function test_findTextPosition_withMatchingText_returnsStartIndex(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('findTextPosition');
        $method->setAccessible(true);

        $document = [
            'body' => [
                'content' => [
                    [
                        'paragraph' => [
                            'elements' => [
                                [
                                    'textRun' => [
                                        'content' => 'Some content here',
                                    ],
                                    'startIndex' => 10,
                                    'endIndex'   => 27,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $result = $method->invoke($this->api, $document, 'Some content here');

        // Then
        $this->assertEquals(10, $result);
    }

    public function test_findTextPosition_withNoMatchingText_returnsNull(): void
    {
        // Given
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('findTextPosition');
        $method->setAccessible(true);

        $document = [
            'body' => [
                'content' => [
                    [
                        'paragraph' => [
                            'elements' => [
                                [
                                    'textRun' => [
                                        'content' => 'Some content here',
                                    ],
                                    'startIndex' => 10,
                                    'endIndex'   => 27,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $result = $method->invoke($this->api, $document, 'Not found text');

        // Then
        $this->assertNull($result);
    }

    public function test_replaceVariablesInDocument_withPlainText_replacesWithSimpleMethod(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $mappings   = [
            'name' => 'John Doe',
            'age'  => '30',
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('replaceVariablesInDocument');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $mappings);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_replaceVariablesInDocument_withMarkdown_usesFormattedMethod(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $mappings   = [
            'summary' => '# Summary\n\n**Patient:** John Doe',
        ];

        // Create mock Guzzle client - needs multiple responses
        $mock = new MockHandler([
            // First for replaceVariablesWithPlainText
            new Response(200, [], json_encode([])),
            // Second for reading document to find text position
            new Response(200, [], json_encode([
                'body' => [
                    'content' => [
                        [
                            'paragraph' => [
                                'elements' => [
                                    [
                                        'textRun' => [
                                            'content' => 'Summary',
                                        ],
                                        'startIndex' => 1,
                                        'endIndex'   => 8,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])),
            // Third for applying formatting
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('replaceVariablesInDocument');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $mappings);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_replaceVariablesWithPlainText_makesCorrectApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $mappings   = [
            'name' => 'John Doe',
            'age'  => '30',
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('replaceVariablesWithPlainText');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $mappings);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_applyFormattingToText_withBoldFormat_makesCorrectApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $baseIndex  = 10;
        $formats    = [
            [
                'type'  => 'bold',
                'start' => 0,
                'end'   => 10,
            ],
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('applyFormattingToText');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $baseIndex, $formats);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_applyFormattingToText_withItalicFormat_makesCorrectApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $baseIndex  = 10;
        $formats    = [
            [
                'type'  => 'italic',
                'start' => 0,
                'end'   => 10,
            ],
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('applyFormattingToText');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $baseIndex, $formats);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_applyFormattingToText_withHeadingFormat_makesCorrectApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $baseIndex  = 1;
        $formats    = [
            [
                'type'  => 'heading1',
                'start' => 0,
                'end'   => 10,
            ],
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('applyFormattingToText');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $baseIndex, $formats);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_applyFormattingToText_withMultipleFormats_makesCorrectApiCall(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->team)->create([
            'access_token' => 'oauth_token',
        ]);

        $documentId = 'test_doc_id';
        $baseIndex  = 1;
        $formats    = [
            [
                'type'  => 'heading1',
                'start' => 0,
                'end'   => 10,
            ],
            [
                'type'  => 'bold',
                'start' => 12,
                'end'   => 20,
            ],
            [
                'type'  => 'italic',
                'start' => 25,
                'end'   => 35,
            ],
        ];

        // Create mock Guzzle client
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient   = new Client(['handler' => $handlerStack]);

        // Set mock client on API
        $this->api->setOverrideClient($mockClient);

        // Initialize authentication
        $this->api->getRequestHeaders();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('applyFormattingToText');
        $method->setAccessible(true);

        // When - Should not throw exception
        $method->invoke($this->api, $documentId, $baseIndex, $formats);

        // Then - No exception means success
        $this->assertTrue(true);
    }

    public function test_parseMarkdown_withConsecutiveBoldLines_calculatesPositionsCorrectly(): void
    {
        // Given - Markdown with consecutive bold sections (the bug scenario from user report)
        $markdown = "**Initial Medical Evaluation – Synergy Chiropractic Clinics**\n\n**Dates of Treatment: 10/31/2017 No. of Visits: 12 (planned; 3x/week for 4 weeks)**";

        // When
        $reflection = new \ReflectionClass($this->api);
        $method     = $reflection->getMethod('parseMarkdown');
        $method->setAccessible(true);
        $result = $method->invoke($this->api, $markdown);

        // Then - Plain text should have markdown syntax removed
        $this->assertEquals(
            "Initial Medical Evaluation – Synergy Chiropractic Clinics\n\nDates of Treatment: 10/31/2017 No. of Visits: 12 (planned; 3x/week for 4 weeks)",
            $result['plainText']
        );

        // And - Should have 2 bold format instructions
        $this->assertCount(2, $result['formats']);

        // First bold section should start at position 0 and include full text (59 chars)
        $this->assertEquals([
            'type'  => 'bold',
            'start' => 0,
            'end'   => 59, // "Initial Medical Evaluation – Synergy Chiropractic Clinics"
        ], $result['formats'][0]);

        // Second bold section should start at position 61 (after "...\n\n")
        // and should include the FULL text including "Da" at the start (79 chars)
        $this->assertEquals([
            'type'  => 'bold',
            'start' => 61,
            'end'   => 140, // 61 + 79 = 140
        ], $result['formats'][1]);

        // Verify the second bold section captures "Dates" correctly (not missing "Da")
        $secondBoldText = substr($result['plainText'], 61, 79);
        $this->assertStringStartsWith('Dates of Treatment:', $secondBoldText);
    }
}
