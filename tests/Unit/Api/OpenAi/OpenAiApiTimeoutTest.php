<?php

namespace Tests\Unit\Api\OpenAi;

use App\Api\OpenAi\OpenAiApi;
use App\Api\Options\ResponsesApiOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

class OpenAiApiTimeoutTest extends AuthenticatedTestCase
{
    private array $requestHistory = [];

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        // Configure OpenAI API credentials for testing
        Config::set('openai.api_url', 'https://api.openai.com/v1');
        Config::set('openai.api_key', 'test-api-key');
    }

    #[Test]
    public function responses_passes_custom_timeout_to_guzzle_request(): void
    {
        // Given: A custom timeout of 120 seconds in options
        $options = new ResponsesApiOptions(['timeout' => 120]);

        // And: A mock Guzzle client that captures request options
        $api = $this->createApiWithMockedClient();

        // When: Making a responses API call
        $api->responses('gpt-4o', [['role' => 'user', 'content' => 'Hello']], $options);

        // Then: The timeout should have been passed to Guzzle
        $this->assertNotEmpty($this->requestHistory, 'Expected at least one request to be made');
        $requestOptions = $this->requestHistory[0]['options'];

        $this->assertArrayHasKey('timeout', $requestOptions, 'Timeout should be in request options');
        $this->assertEquals(120, $requestOptions['timeout'], 'Timeout should be 120 seconds');
    }

    #[Test]
    public function responses_uses_default_timeout_when_not_specified_in_options(): void
    {
        // Given: Options without a custom timeout
        $options = new ResponsesApiOptions([]);

        // And: A mock Guzzle client that captures request options
        $api = $this->createApiWithMockedClient();

        // When: Making a responses API call
        $api->responses('gpt-4o', [['role' => 'user', 'content' => 'Hello']], $options);

        // Then: The default timeout (300s for OpenAiApi) should be in the HTTP options
        $this->assertNotEmpty($this->requestHistory, 'Expected at least one request to be made');
        $requestOptions = $this->requestHistory[0]['options'];

        // The timeout should always be explicitly set (either custom or default)
        $this->assertArrayHasKey('timeout', $requestOptions, 'Timeout should always be explicitly set');
        $this->assertEquals(300, $requestOptions['timeout'], 'Default timeout should be 300 seconds (5 minutes)');
    }

    #[Test]
    public function stream_responses_passes_custom_timeout_to_guzzle_request(): void
    {
        // Given: A custom timeout of 180 seconds in options
        $options = new ResponsesApiOptions(['timeout' => 180]);

        // And: A mock Guzzle client that captures request options
        $api = $this->createApiWithMockedClient();

        // And: A mock stream message (we need to create a minimal mock for this)
        $mockStreamMessage = $this->createMockStreamMessage();

        // When: Making a streaming responses API call
        $api->streamResponses('gpt-4o', [['role' => 'user', 'content' => 'Hello']], $options, $mockStreamMessage);

        // Then: The timeout should have been passed to Guzzle
        $this->assertNotEmpty($this->requestHistory, 'Expected at least one request to be made');
        $requestOptions = $this->requestHistory[0]['options'];

        $this->assertArrayHasKey('timeout', $requestOptions, 'Timeout should be in request options');
        $this->assertEquals(180, $requestOptions['timeout'], 'Timeout should be 180 seconds');
    }

    #[Test]
    public function responses_api_options_stores_and_retrieves_timeout(): void
    {
        // Given: Creating options with a timeout
        $options = new ResponsesApiOptions(['timeout' => 240]);

        // Then: The timeout should be retrievable
        $this->assertEquals(240, $options->getTimeout());
    }

    #[Test]
    public function responses_api_options_timeout_is_null_by_default(): void
    {
        // Given: Creating options without a timeout
        $options = new ResponsesApiOptions([]);

        // Then: The timeout should be null
        $this->assertNull($options->getTimeout());
    }

    #[Test]
    public function responses_api_options_can_set_timeout_via_method(): void
    {
        // Given: Creating options and setting timeout via method
        $options = new ResponsesApiOptions([]);
        $options->setTimeout(300);

        // Then: The timeout should be retrievable
        $this->assertEquals(300, $options->getTimeout());
    }

    #[Test]
    public function responses_timeout_actually_fires_with_short_timeout(): void
    {
        // Given: A very short timeout (1 second - minimum integer value)
        $options = new ResponsesApiOptions(['timeout' => 1]);

        // And: A real OpenAiApi instance (will hit actual API)
        $api = new OpenAiApi();

        // Then: We expect a timeout-related exception
        $this->expectException(\Newms87\Danx\Exceptions\ApiRequestException::class);

        // Track time to ensure we don't hang
        $start = microtime(true);

        try {
            // When: Making a real API call with tiny timeout
            // The request should timeout before OpenAI can respond
            $api->responses('gpt-4o', [['role' => 'user', 'content' => 'test']], $options);
        } finally {
            $elapsed = microtime(true) - $start;
            // Should complete (with error) within 5 seconds max (1s timeout + network latency buffer)
            $this->assertLessThan(5.0, $elapsed, "Request should timeout quickly, not hang. Took {$elapsed}s");
        }
    }

    /**
     * Create an OpenAiApi instance with a mocked Guzzle client that captures requests
     */
    private function createApiWithMockedClient(): OpenAiApi
    {
        // Create a mock response
        $mockResponse = new Response(200, [], json_encode([
            'id' => 'resp_123',
            'object' => 'response',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Hello! How can I help you?',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 20,
            ],
        ]));

        // Create mock handler and history middleware
        $mock = new MockHandler([$mockResponse]);
        $this->requestHistory = [];
        $history = Middleware::history($this->requestHistory);

        // Create handler stack with history middleware
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        // Create mock client
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create API instance and set mock client
        $api = new OpenAiApi();
        $api->setOverrideClient($mockClient);

        return $api;
    }

    /**
     * Create a mock AgentThreadMessage for streaming tests
     */
    private function createMockStreamMessage(): \App\Models\Agent\AgentThreadMessage
    {
        // Create a real message in the database for the streaming test
        $agentThread = \App\Models\Agent\AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        return \App\Models\Agent\AgentThreadMessage::factory()->create([
            'agent_thread_id' => $agentThread->id,
            'role' => 'assistant',
            'content' => '',
        ]);
    }
}
