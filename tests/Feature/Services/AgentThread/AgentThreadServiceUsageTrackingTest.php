<?php

namespace Tests\Feature\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Usage\UsageEvent;
use App\Services\AgentThread\AgentThreadService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentThreadServiceUsageTrackingTest extends TestCase
{
    protected AgentThreadService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(AgentThreadService::class);
    }

    #[Test]
    public function it_tracks_usage_when_handling_ai_response()
    {
        $agent = Agent::factory()->create(['model' => 'gpt-4o']);

        $thread    = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $threadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
        ]);

        // Mock the response
        $response = $this->mock(AgentCompletionResponseContract::class);
        $response->shouldReceive('inputTokens')->andReturn(200);
        $response->shouldReceive('outputTokens')->andReturn(100);
        $response->shouldReceive('getContent')->andReturn('Test response');
        $response->shouldReceive('getDataFields')->andReturn([]);
        $response->shouldReceive('getResponseId')->andReturn('test-123');
        $response->shouldReceive('toArray')->andReturn(['id' => 'test-123']);
        $response->shouldReceive('isFinished')->andReturn(true);

        // Handle the response
        $this->service->handleResponse($thread, $threadRun, $response);

        // Check that usage event was created
        $usageEvent = UsageEvent::where('object_type', AgentThreadRun::class)
            ->where('object_id', $threadRun->id)
            ->first();

        $this->assertNotNull($usageEvent);
        $this->assertEquals(OpenAiApi::class, $usageEvent->api_name);
        $this->assertEquals('ai_completion', $usageEvent->event_type);
        $this->assertEquals(200, $usageEvent->input_tokens);
        $this->assertEquals(100, $usageEvent->output_tokens);
        $this->assertEquals('gpt-4o', $usageEvent->metadata['model']);

        // Check that thread run usage is accessible via trait
        $threadRun->refresh();
        $usage = $threadRun->usage;
        $this->assertNotNull($usage);
        $this->assertEquals(200, $usage['input_tokens']); // From the response
        $this->assertEquals(100, $usage['output_tokens']); // From the response
    }

    #[Test]
    public function it_does_not_track_usage_when_no_tokens_consumed()
    {
        $agent = Agent::factory()->create([
            'model' => 'gpt-4o',
        ]);

        $thread    = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $threadRun = AgentThreadRun::factory()->create(['agent_thread_id' => $thread->id]);

        // Mock response with zero tokens
        $response = $this->mock(AgentCompletionResponseContract::class);
        $response->shouldReceive('inputTokens')->andReturn(0);
        $response->shouldReceive('outputTokens')->andReturn(0);
        $response->shouldReceive('getContent')->andReturn('');
        $response->shouldReceive('getDataFields')->andReturn([]);
        $response->shouldReceive('getResponseId')->andReturn(null);
        $response->shouldReceive('toArray')->andReturn([]);
        $response->shouldReceive('isFinished')->andReturn(true);

        $this->service->handleResponse($thread, $threadRun, $response);

        // No usage event should be created
        $usageEvent = UsageEvent::where('object_type', AgentThreadRun::class)
            ->where('object_id', $threadRun->id)
            ->first();

        $this->assertNull($usageEvent);
    }

    #[Test]
    public function it_calculates_costs_correctly_when_tracking_usage()
    {
        // Set up pricing config (per token, not per 1000 tokens)
        config([
            'ai.models.gpt-4o' => [
                'api'    => OpenAiApi::class,
                'input'  => 0.0025 / 1000,  // 0.0025 per 1000 tokens = 0.0000025 per token
                'output' => 0.01 / 1000,   // 0.01 per 1000 tokens = 0.00001 per token
            ],
        ]);

        $agent = Agent::factory()->create([
            'model' => 'gpt-4o',
        ]);

        $thread    = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $threadRun = AgentThreadRun::factory()->create(['agent_thread_id' => $thread->id]);

        // Mock response
        $response = $this->mock(AgentCompletionResponseContract::class);
        $response->shouldReceive('inputTokens')->andReturn(1000);
        $response->shouldReceive('outputTokens')->andReturn(500);
        $response->shouldReceive('getContent')->andReturn('Test response');
        $response->shouldReceive('getDataFields')->andReturn([]);
        $response->shouldReceive('getResponseId')->andReturn(null);
        $response->shouldReceive('toArray')->andReturn([]);
        $response->shouldReceive('isFinished')->andReturn(true);

        $this->service->handleResponse($thread, $threadRun, $response);

        $usageEvent = UsageEvent::where('object_type', AgentThreadRun::class)
            ->where('object_id', $threadRun->id)
            ->first();

        $this->assertNotNull($usageEvent);
        $this->assertEquals(0.0025, $usageEvent->input_cost); // 1000 * 0.0025
        $this->assertEquals(0.005, $usageEvent->output_cost); // 500 * 0.01
    }

    #[Test]
    public function it_includes_api_response_in_metadata()
    {
        $agent = Agent::factory()->create([
            'model' => 'gpt-4o',
        ]);

        $thread    = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $threadRun = AgentThreadRun::factory()->create(['agent_thread_id' => $thread->id]);

        $apiResponseData = [
            'id'    => 'test-123',
            'model' => 'gpt-4o',
            'usage' => ['total_tokens' => 300],
        ];

        // Mock response
        $response = $this->mock(AgentCompletionResponseContract::class);
        $response->shouldReceive('inputTokens')->andReturn(200);
        $response->shouldReceive('outputTokens')->andReturn(100);
        $response->shouldReceive('getContent')->andReturn('Test response');
        $response->shouldReceive('getDataFields')->andReturn([]);
        $response->shouldReceive('getResponseId')->andReturn('test-123');
        $response->shouldReceive('toArray')->andReturn($apiResponseData);
        $response->shouldReceive('isFinished')->andReturn(true);

        $this->service->handleResponse($thread, $threadRun, $response);

        $usageEvent = UsageEvent::where('object_type', AgentThreadRun::class)
            ->where('object_id', $threadRun->id)
            ->first();

        $this->assertNotNull($usageEvent);
        $this->assertEquals($apiResponseData, $usageEvent->metadata['api_response']);
    }

}
