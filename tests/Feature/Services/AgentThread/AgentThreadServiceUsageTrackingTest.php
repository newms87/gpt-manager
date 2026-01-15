<?php

namespace Tests\Feature\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Usage\UsageEvent;
use App\Services\AgentThread\AgentThreadService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\TestAiApi;
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
        $agent = Agent::factory()->create(['model' => self::TEST_MODEL]);

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
        $this->assertEquals(TestAiApi::class, $usageEvent->api_name);
        $this->assertEquals('ai_completion', $usageEvent->event_type);
        $this->assertEquals(200, $usageEvent->input_tokens);
        $this->assertEquals(100, $usageEvent->output_tokens);
        $this->assertEquals(self::TEST_MODEL, $usageEvent->metadata['model']);

        // Check that thread run usage is accessible via trait
        $threadRun->refresh();
        $summary = $threadRun->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(200, $summary->input_tokens); // From the response
        $this->assertEquals(100, $summary->output_tokens); // From the response
    }

    #[Test]
    public function it_does_not_track_usage_when_no_tokens_consumed()
    {
        $agent = Agent::factory()->create([
            'model' => self::TEST_MODEL,
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
        // Uses test model pricing from TestCase::configureTestModel()
        // input: 1.00 / 1_000_000 per token, output: 2.00 / 1_000_000 per token

        $agent = Agent::factory()->create([
            'model' => self::TEST_MODEL,
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
        // Cost = tokens * (rate / 1_000_000)
        // Input: 1000 * (1.00 / 1_000_000) = 0.001
        // Output: 500 * (2.00 / 1_000_000) = 0.001
        $this->assertEquals(0.001, $usageEvent->input_cost);
        $this->assertEquals(0.001, $usageEvent->output_cost);
    }

    #[Test]
    public function it_includes_api_response_in_metadata()
    {
        $agent = Agent::factory()->create([
            'model' => self::TEST_MODEL,
        ]);

        $thread    = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $threadRun = AgentThreadRun::factory()->create(['agent_thread_id' => $thread->id]);

        $apiResponseData = [
            'id'    => 'test-123',
            'model' => self::TEST_MODEL,
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
