<?php

namespace Tests\Feature\Services\AgentThread;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Resources\Agent\MessageResource;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Config;
use Newms87\Danx\Models\Audit\ApiLog;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Api\TestAi\TestAiApi;

class AgentThreadMessageApiLogTest extends AuthenticatedTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Configure test-model for testing
        Config::set('ai.models.test-model', [
            'api'     => TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
        ]);
    }

    #[Test]
    public function agent_thread_message_has_api_log_relationship(): void
    {
        // Given
        $apiLog = ApiLog::create([
            'api_class'        => 'TestClass',
            'service_name'     => 'TestService',
            'method'           => 'POST',
            'url'              => 'https://api.test.com/endpoint',
            'status_code'      => 200,
            'request'          => ['test' => 'request'],
            'response'         => ['test' => 'response'],
            'request_headers'  => ['Content-Type' => 'application/json'],
            'response_headers' => [],
            'started_at'       => now(),
            'finished_at'      => now(),
        ]);

        $thread  = AgentThread::factory()->create();
        $message = $thread->messages()->create([
            'role'       => AgentThreadMessage::ROLE_ASSISTANT,
            'content'    => 'Test message',
            'api_log_id' => $apiLog->id,
        ]);

        // When
        $retrievedApiLog = $message->apiLog;

        // Then
        $this->assertInstanceOf(ApiLog::class, $retrievedApiLog);
        $this->assertEquals($apiLog->id, $retrievedApiLog->id);
        $this->assertEquals('TestClass', $retrievedApiLog->api_class);
        $this->assertEquals('TestService', $retrievedApiLog->service_name);
        $this->assertEquals(200, $retrievedApiLog->status_code);
    }

    #[Test]
    public function agent_thread_message_can_be_created_with_api_log_id(): void
    {
        // Given
        $apiLog = ApiLog::create([
            'api_class'        => 'TestClass',
            'service_name'     => 'TestService',
            'method'           => 'POST',
            'url'              => 'https://api.test.com/endpoint',
            'status_code'      => 200,
            'request'          => ['test' => 'request'],
            'response'         => ['test' => 'response'],
            'request_headers'  => ['Content-Type' => 'application/json'],
            'response_headers' => [],
            'started_at'       => now(),
            'finished_at'      => now(),
        ]);

        $thread = AgentThread::factory()->create();

        // When
        $message = $thread->messages()->create([
            'role'       => AgentThreadMessage::ROLE_ASSISTANT,
            'content'    => 'Test message with API log',
            'api_log_id' => $apiLog->id,
        ]);

        // Then
        $this->assertNotNull($message->api_log_id);
        $this->assertEquals($apiLog->id, $message->api_log_id);

        // Verify it's persisted in database
        $this->assertDatabaseHas('agent_thread_messages', [
            'id'         => $message->id,
            'api_log_id' => $apiLog->id,
        ]);
    }

    #[Test]
    public function message_resource_includes_api_log_when_present(): void
    {
        // Given
        $apiLog = ApiLog::create([
            'api_class'        => 'OpenAI\Api',
            'service_name'     => 'OpenAI',
            'method'           => 'POST',
            'url'              => 'https://api.openai.com/v1/chat/completions',
            'status_code'      => 200,
            'request'          => ['model' => 'gpt-4', 'messages' => []],
            'response'         => ['choices' => []],
            'request_headers'  => ['Authorization' => 'Bearer ***'],
            'response_headers' => ['Content-Type' => 'application/json'],
            'started_at'       => now(),
            'finished_at'      => now(),
        ]);

        $thread  = AgentThread::factory()->create();
        $message = $thread->messages()->create([
            'role'       => AgentThreadMessage::ROLE_ASSISTANT,
            'content'    => 'Test response',
            'api_log_id' => $apiLog->id,
        ]);

        // Reload to ensure relationship is loaded
        $message = $message->fresh('apiLog');

        // When
        $resource = MessageResource::make($message, [
            'id'      => true,
            'content' => true,
            'apiLog'  => ['id' => true, 'api_class' => true, 'service_name' => true, 'status_code' => true],
        ]);

        // Then
        $this->assertIsArray($resource);
        $this->assertArrayHasKey('apiLog', $resource);
        $this->assertNotNull($resource['apiLog']);
        $this->assertIsArray($resource['apiLog']);
        $this->assertEquals($apiLog->id, $resource['apiLog']['id']);
        $this->assertEquals('OpenAI\Api', $resource['apiLog']['api_class']);
        $this->assertEquals('OpenAI', $resource['apiLog']['service_name']);
        $this->assertEquals(200, $resource['apiLog']['status_code']);
    }

    #[Test]
    public function message_resource_returns_null_when_no_api_log(): void
    {
        // Given
        $thread  = AgentThread::factory()->create();
        $message = $thread->messages()->create([
            'role'       => AgentThreadMessage::ROLE_USER,
            'content'    => 'User message without API log',
            'api_log_id' => null,
        ]);

        // When
        $resource = MessageResource::make($message, [
            'id'      => true,
            'content' => true,
            'apiLog'  => true,
        ]);

        // Then
        $this->assertIsArray($resource);
        $this->assertArrayHasKey('apiLog', $resource);
        $this->assertNull($resource['apiLog']);
    }

    #[Test]
    public function handle_response_stores_api_log_id_when_available(): void
    {
        // Given
        $apiLog = ApiLog::create([
            'api_class'        => 'Anthropic\Api',
            'service_name'     => 'Anthropic',
            'method'           => 'POST',
            'url'              => 'https://api.anthropic.com/v1/messages',
            'status_code'      => 200,
            'request'          => ['model' => 'claude-3-5-sonnet-20241022', 'messages' => []],
            'response'         => ['content' => []],
            'request_headers'  => ['x-api-key' => '***'],
            'response_headers' => ['Content-Type' => 'application/json'],
            'started_at'       => now(),
            'finished_at'      => now(),
        ]);

        $thread    = AgentThread::factory()->withMessages(1)->create();
        $threadRun = $thread->runs()->create([
            'status'          => 'running',
            'response_format' => 'text',
            'started_at'      => now(),
        ]);

        // Create a mock response
        $response = $this->createMock(\App\Api\AgentApiContracts\AgentCompletionResponseContract::class);
        $response->method('isMessageEmpty')->willReturn(false);
        $response->method('getContent')->willReturn('Test AI response');
        $response->method('inputTokens')->willReturn(100);
        $response->method('outputTokens')->willReturn(200);
        $response->method('isFinished')->willReturn(true);
        $response->method('getDataFields')->willReturn([]);
        $response->method('getResponseId')->willReturn('resp_123456');
        $response->method('toArray')->willReturn(['content' => 'Test AI response']);

        $service = app(AgentThreadService::class);

        // Use reflection to set the protected currentApiLogId property
        $reflection = new ReflectionClass($service);
        $property   = $reflection->getProperty('currentApiLogId');
        $property->setAccessible(true);
        $property->setValue($service, $apiLog->id);

        // When
        $service->handleResponse($thread, $threadRun, $response);

        // Then
        $lastMessage = $thread->messages()->latest()->first();
        $this->assertNotNull($lastMessage);
        $this->assertEquals($apiLog->id, $lastMessage->api_log_id);

        // Verify the api_log_id is persisted in database
        $this->assertDatabaseHas('agent_thread_messages', [
            'id'         => $lastMessage->id,
            'role'       => AgentThreadMessage::ROLE_ASSISTANT,
            'api_log_id' => $apiLog->id,
        ]);
    }

    #[Test]
    public function handle_response_creates_message_without_api_log_when_not_available(): void
    {
        // Given
        $thread    = AgentThread::factory()->withMessages(1)->create();
        $threadRun = $thread->runs()->create([
            'status'          => 'running',
            'response_format' => 'text',
            'started_at'      => now(),
        ]);

        // Create a mock response
        $response = $this->createMock(\App\Api\AgentApiContracts\AgentCompletionResponseContract::class);
        $response->method('isMessageEmpty')->willReturn(false);
        $response->method('getContent')->willReturn('Test AI response without API log');
        $response->method('inputTokens')->willReturn(50);
        $response->method('outputTokens')->willReturn(75);
        $response->method('isFinished')->willReturn(true);
        $response->method('getDataFields')->willReturn([]);
        $response->method('getResponseId')->willReturn('resp_789012');
        $response->method('toArray')->willReturn(['content' => 'Test AI response without API log']);

        $service = app(AgentThreadService::class);

        // Don't set currentApiLogId - it should remain null

        // When
        $service->handleResponse($thread, $threadRun, $response);

        // Then
        $lastMessage = $thread->messages()->latest()->first();
        $this->assertNotNull($lastMessage);
        $this->assertNull($lastMessage->api_log_id);

        // Verify the api_log_id is null in database
        $this->assertDatabaseHas('agent_thread_messages', [
            'id'         => $lastMessage->id,
            'role'       => AgentThreadMessage::ROLE_ASSISTANT,
            'api_log_id' => null,
        ]);
    }

    #[Test]
    public function current_api_log_id_is_cleared_after_use(): void
    {
        // Given
        $apiLog = ApiLog::create([
            'api_class'        => 'TestApi',
            'service_name'     => 'TestService',
            'method'           => 'POST',
            'url'              => 'https://api.test.com/v1/test',
            'status_code'      => 200,
            'request'          => ['data' => 'test'],
            'response'         => ['result' => 'success'],
            'request_headers'  => [],
            'response_headers' => [],
            'started_at'       => now(),
            'finished_at'      => now(),
        ]);

        $thread    = AgentThread::factory()->withMessages(1)->create();
        $threadRun = $thread->runs()->create([
            'status'          => 'running',
            'response_format' => 'text',
            'started_at'      => now(),
        ]);

        // Create a mock response
        $response = $this->createMock(\App\Api\AgentApiContracts\AgentCompletionResponseContract::class);
        $response->method('isMessageEmpty')->willReturn(false);
        $response->method('getContent')->willReturn('First response');
        $response->method('inputTokens')->willReturn(100);
        $response->method('outputTokens')->willReturn(150);
        $response->method('isFinished')->willReturn(true);
        $response->method('getDataFields')->willReturn([]);
        $response->method('getResponseId')->willReturn('resp_first');
        $response->method('toArray')->willReturn(['content' => 'First response']);

        $service = app(AgentThreadService::class);

        // Use reflection to set and verify the protected currentApiLogId property
        $reflection = new ReflectionClass($service);
        $property   = $reflection->getProperty('currentApiLogId');
        $property->setAccessible(true);
        $property->setValue($service, $apiLog->id);

        // Verify it's set
        $this->assertEquals($apiLog->id, $property->getValue($service));

        // When
        $service->handleResponse($thread, $threadRun, $response);

        // Then - currentApiLogId should be cleared after use
        $this->assertNull($property->getValue($service));

        // Create another message to ensure it doesn't inherit the previous API log ID
        $threadRun2 = $thread->runs()->create([
            'status'          => 'running',
            'response_format' => 'text',
            'started_at'      => now(),
        ]);

        $response2 = $this->createMock(\App\Api\AgentApiContracts\AgentCompletionResponseContract::class);
        $response2->method('isMessageEmpty')->willReturn(false);
        $response2->method('getContent')->willReturn('Second response');
        $response2->method('inputTokens')->willReturn(50);
        $response2->method('outputTokens')->willReturn(75);
        $response2->method('isFinished')->willReturn(true);
        $response2->method('getDataFields')->willReturn([]);
        $response2->method('getResponseId')->willReturn('resp_second');
        $response2->method('toArray')->willReturn(['content' => 'Second response']);

        $service->handleResponse($thread, $threadRun2, $response2);

        // Verify the second message doesn't have the API log ID
        $lastMessage = $thread->messages()->latest()->first();
        $this->assertNull($lastMessage->api_log_id);
    }
}
