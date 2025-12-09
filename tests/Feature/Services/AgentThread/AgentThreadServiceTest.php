<?php

namespace Tests\Feature\Services\AgentThread;

use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Api\TestAi\TestAiApi;

class AgentThreadServiceTest extends AuthenticatedTestCase
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

    public function test_dispatch_setsJobDispatch()
    {
        // Given
        Queue::fake();
        $thread  = AgentThread::factory()->withMessages(1)->create();
        $service = new AgentThreadService();

        // When
        $threadRun = $service->dispatch($thread);

        // Then
        $this->assertNotNull($threadRun->job_dispatch_id);
    }

    public function test_dispatch_createsThreadRunSuccessfully()
    {
        // Given
        $agent       = Agent::factory()->create();
        $agentThread = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $agentThread->messages()->create(['role' => AgentThreadMessage::ROLE_USER, 'content' => 'Test message']);

        $service = new AgentThreadService();

        // When
        $threadRun = $service->dispatch($agentThread);

        // Then
        $this->assertEquals(AgentThreadRun::STATUS_RUNNING, $threadRun->status);
        $this->assertEquals(AgentThreadRun::RESPONSE_FORMAT_TEXT, $threadRun->response_format);
    }

    public function test_dispatch_throwsExceptionWhenThreadIsAlreadyRunning()
    {
        // Given
        $agentThreadRun = AgentThreadRun::factory()->create(['status' => AgentThreadRun::STATUS_RUNNING]);
        $service        = new AgentThreadService();

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('already running');

        // When
        $service->dispatch($agentThreadRun->agentThread);
    }

    public function test_dispatch_dispatchesJobAndRunsThread()
    {
        // Given
        Queue::fake();
        $thread  = AgentThread::factory()->withMessages(1)->create();
        $service = new AgentThreadService();

        // When
        $threadRun = $service->dispatch($thread);

        // Then
        Queue::assertPushed(ExecuteThreadRunJob::class, function ($job) use ($threadRun) {
            return $job->threadRun->id === $threadRun->id;
        });
        $this->assertEquals(AgentThreadRun::STATUS_RUNNING, $threadRun->status);
    }

    public function test_run_executesThreadRunWhenRunImmediately()
    {
        // Given
        $thread  = AgentThread::factory()->withMessages(1)->create();
        $service = $this->mock(AgentThreadService::class)->makePartial();
        $service->shouldReceive('executeThreadRun')->once();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertEquals(AgentThreadRun::STATUS_RUNNING, $threadRun->status);
    }

    public function test_run_setsResponseFormatToJsonSchemaWithCorrectSchemaAndFragment()
    {
        // Given
        $agent             = Agent::factory()->create();
        $thread            = AgentThread::factory()->withMessages(1)->create(['agent_id' => $agent->id]);
        $schemaAssociation = SchemaAssociation::factory()->withSchema(['type' => 'object', 'properties' => []])->create();
        $service           = new AgentThreadService();

        // When
        $threadRun = $service->withResponseFormat($schemaAssociation->schemaDefinition, $schemaAssociation->schemaFragment)->run($thread);

        // Then
        $this->assertEquals(AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA, $threadRun->response_format);
        $this->assertEquals($schemaAssociation->schema_definition_id, $threadRun->response_schema_id);
        $this->assertEquals($schemaAssociation->schema_fragment_id, $threadRun->response_fragment_id);
    }

    #[Test]
    public function json_format_message_added_for_json_schema_response(): void
    {
        // Given: AgentThreadRun with json_schema response format
        $schemaDefinition = SchemaDefinition::factory()->create([
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            'type'   => SchemaDefinition::TYPE_AGENT_RESPONSE,
        ]);

        $agentThreadRun = AgentThreadRun::factory()->create([
            'response_format'    => AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA,
            'response_schema_id' => $schemaDefinition->id,
        ]);

        $service = app(AgentThreadService::class);

        // When: Getting response message
        $responseMessage = $service->getResponseMessage($agentThreadRun);

        // Then: The JSON format message SHOULD be added for json_schema format
        $this->assertStringContainsString('OUTPUT IN JSON FORMAT ONLY', $responseMessage);
    }

    #[Test]
    public function json_format_message_added_for_text_response_with_json_object(): void
    {
        // Given: AgentThreadRun with json_object response format (not json_schema)
        $agentThreadRun = AgentThreadRun::factory()->create([
            'response_format' => 'json_object', // Different from json_schema
        ]);

        $service = app(AgentThreadService::class);

        // When: Getting response message
        $responseMessage = $service->getResponseMessage($agentThreadRun);

        // Then: The JSON format message SHOULD be added for json_object format
        $this->assertStringContainsString('OUTPUT IN JSON FORMAT ONLY', $responseMessage);
    }

    #[Test]
    public function json_format_message_not_added_for_text_response(): void
    {
        // Given: AgentThreadRun with text response format
        $agentThreadRun = AgentThreadRun::factory()->create([
            'response_format' => AgentThreadRun::RESPONSE_FORMAT_TEXT,
        ]);

        $service = app(AgentThreadService::class);

        // When: Getting response message
        $responseMessage = $service->getResponseMessage($agentThreadRun);

        // Then: No JSON format message should be added for text format
        $this->assertStringNotContainsString('OUTPUT IN JSON FORMAT ONLY', $responseMessage);
    }
}
