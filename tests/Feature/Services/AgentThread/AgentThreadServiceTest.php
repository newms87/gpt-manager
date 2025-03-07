<?php

namespace Tests\Feature\Services\AgentThread;

use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JsonSchemaService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;

class AgentThreadServiceTest extends AuthenticatedTestCase
{
    public function test_run_createsThreadRunSuccessfully()
    {
        // Given
        $temperature = .7;
        $agent       = Agent::factory()->create([
            'temperature'     => $temperature,
            'response_format' => Agent::RESPONSE_FORMAT_JSON_OBJECT,
        ]);
        $thread      = AgentThread::factory()->create(['agent_id' => $agent->id]);
        $thread->messages()->create(['role' => AgentThreadMessage::ROLE_USER, 'content' => 'Test message']);

        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertEquals(AgentThreadRun::STATUS_RUNNING, $threadRun->status);
        $this->assertEquals($temperature, $threadRun->temperature);
        $this->assertEquals($agent->tools, $threadRun->tools);
        $this->assertEquals(Agent::RESPONSE_FORMAT_JSON_OBJECT, $threadRun->response_format);
    }

    public function test_run_throwsExceptionWhenThreadIsAlreadyRunning()
    {
        // Given
        $threadRun = AgentThreadRun::factory()->create(['status' => AgentThreadRun::STATUS_RUNNING]);
        $service   = new AgentThreadService();

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('The thread is already running.');

        // When
        $service->run($threadRun->agentThread);
    }

    public function test_run_dispatchesJobWhenDispatchIsTrue()
    {
        // Given
        Queue::fake();
        $thread  = AgentThread::factory()->withMessages(1)->create();
        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        Queue::assertPushed(ExecuteThreadRunJob::class, function ($job) use ($threadRun) {
            return $job->threadRun->id === $threadRun->id;
        });
        $this->assertEquals(AgentThreadRun::STATUS_RUNNING, $threadRun->status);
    }

    public function test_run_executesThreadRunWhenDispatchIsFalse()
    {
        // Given
        $thread  = AgentThread::factory()->withMessages(1)->create();
        $service = Mockery::mock(AgentThreadService::class)->makePartial();
        $service->shouldReceive('executeThreadRun')->once();

        // When
        $threadRun = $service->run($thread, false);

        // Then
        $this->assertEquals(AgentThreadRun::STATUS_RUNNING, $threadRun->status);
    }

    public function test_run_setsResponseFormatToTextWhenAgentResponseFormatIsText()
    {
        // Given
        $agent   = Agent::factory()->create(['response_format' => Agent::RESPONSE_FORMAT_TEXT]);
        $thread  = AgentThread::factory()->withMessages(1)->create(['agent_id' => $agent->id]);
        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertEquals(Agent::RESPONSE_FORMAT_TEXT, $threadRun->response_format);
    }

    public function test_run_setsJobDispatchIdWhenDispatchIsTrue()
    {
        // Given
        Queue::fake();
        $thread  = AgentThread::factory()->withMessages(1)->create();
        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertNotNull($threadRun->job_dispatch_id);
    }

    public function test_cleanContent_providesValidJsonWithExtraBackticksPresent(): void
    {
        // Given
        $content = "```json\n{\"key\": \"value\"}\n```";
        $message = new AgentThreadMessage(['content' => $content]);

        // When
        $cleanedContent = $message->getCleanContent();

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }

    public function test_cleanContent_providesValidJsonWithoutExtraBackticksPresent(): void
    {
        // Given
        $content = "{\"key\": \"value\"}";
        $message = new AgentThreadMessage(['content' => $content]);

        // When
        $cleanedContent = $message->getCleanContent();

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }

    public function test_formatResponseSchema_providesValidJsonSchema(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type' => 'string',
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type' => ['string', 'null'],
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchema_requiresAllPropertiesOfNestedObjects(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type'       => 'object',
                'properties' => [
                    'nested_a' => [
                        'type' => 'string',
                    ],
                    'nested_b' => [
                        'type'       => 'object',
                        'properties' => [
                            'nested-key' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'                 => ['object', 'null'],
                        'properties'           => [
                            'nested_a' => [
                                'type' => ['string', 'null'],
                            ],
                            'nested_b' => [
                                'type'                 => ['object', 'null'],
                                'properties'           => [
                                    'nested-key' => [
                                        'type' => ['string', 'null'],
                                    ],
                                ],
                                'required'             => ['nested-key'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required'             => ['nested_a', 'nested_b'],
                        'additionalProperties' => false,
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchema_requiresAllPropertiesOfNestedArrays(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'nested_a' => [
                            'type' => 'string',
                        ],
                        'nested_b' => [
                            'type'       => 'object',
                            'properties' => [
                                'nested-key' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'  => ['array', 'null'],
                        'items' => [
                            'type'                 => ['object', 'null'],
                            'properties'           => [
                                'nested_a' => [
                                    'type' => ['string', 'null'],
                                ],
                                'nested_b' => [
                                    'type'                 => ['object', 'null'],
                                    'properties'           => [
                                        'nested-key' => [
                                            'type' => ['string', 'null'],
                                        ],
                                    ],
                                    'required'             => ['nested-key'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'required'             => ['nested_a', 'nested_b'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchema_addsDescriptionToProperties(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type'        => 'string',
                'description' => 'A test description',
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'        => ['string', 'null'],
                        'description' => 'A test description',
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchema_addsEnumToProperties()
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type' => 'string',
                'enum' => ['value1', 'value2'],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type' => ['string', 'null'],
                        'enum' => ['value1', 'value2'],
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }
}
