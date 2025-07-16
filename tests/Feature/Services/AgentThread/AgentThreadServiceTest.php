<?php

namespace Tests\Feature\Services\AgentThread;

use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaAssociation;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Newms87\Danx\Exceptions\ValidationError;
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
        $service = Mockery::mock(AgentThreadService::class)->makePartial();
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
}
