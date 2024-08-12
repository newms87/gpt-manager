<?php

namespace Tests\Feature\Workflow;

use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use App\Services\AgentThread\AgentThreadService;
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
            'response_format' => 'json_object',
        ]);
        $thread      = Thread::factory()->create(['agent_id' => $agent->id]);
        $thread->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Test message']);

        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertEquals(ThreadRun::STATUS_RUNNING, $threadRun->status);
        $this->assertEquals($temperature, $threadRun->temperature);
        $this->assertEquals($agent->tools, $threadRun->tools);
        $this->assertEquals('json_object', $threadRun->response_format);
    }

    public function test_run_throwsExceptionWhenThreadIsAlreadyRunning()
    {
        // Given
        $threadRun = ThreadRun::factory()->create(['status' => ThreadRun::STATUS_RUNNING]);
        $service   = new AgentThreadService();

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('The thread is already running.');

        // When
        $service->run($threadRun->thread);
    }

    public function test_run_throwsExceptionWhenThreadHasNoMessages()
    {
        // Given
        $thread  = Thread::factory()->create();
        $service = new AgentThreadService();

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You must add messages to the thread before running it.');

        // When
        $service->run($thread);
    }

    public function test_run_dispatchesJobWhenDispatchIsTrue()
    {
        // Given
        Queue::fake();
        $thread  = Thread::factory()->withMessages(1)->create();
        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        Queue::assertPushed(ExecuteThreadRunJob::class, function ($job) use ($threadRun) {
            return $job->threadRun->id === $threadRun->id;
        });
        $this->assertEquals(ThreadRun::STATUS_RUNNING, $threadRun->status);
    }

    public function test_run_executesThreadRunWhenDispatchIsFalse()
    {
        // Given
        $thread  = Thread::factory()->withMessages(1)->create();
        $service = Mockery::mock(AgentThreadService::class)->makePartial();
        $service->shouldReceive('executeThreadRun')->once();

        // When
        $threadRun = $service->run($thread, false);

        // Then
        $this->assertEquals(ThreadRun::STATUS_RUNNING, $threadRun->status);
    }

    public function test_run_setsResponseFormatToTextWhenAgentResponseFormatIsText()
    {
        // Given
        $agent   = Agent::factory()->create(['response_format' => 'text']);
        $thread  = Thread::factory()->withMessages(1)->create(['agent_id' => $agent->id]);
        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertEquals('text', $threadRun->response_format);
    }

    public function test_run_setsJobDispatchIdWhenDispatchIsTrue()
    {
        // Given
        Queue::fake();
        $thread  = Thread::factory()->withMessages(1)->create();
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
        $message = new Message(['content' => $content]);

        // When
        $cleanedContent = $message->getCleanContent();

        // Then
        $this->assertEquals('{"key": "value"}', $cleanedContent);
    }

    public function test_cleanContent_providesValidJsonWithoutExtraBackticksPresent(): void
    {
        // Given
        $content = "{\"key\": \"value\"}";
        $message = new Message(['content' => $content]);

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
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type' => 'string',
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
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'                 => 'object',
                        'properties'           => [
                            'nested_a' => [
                                'type' => 'string',
                            ],
                            'nested_b' => [
                                'type'                 => 'object',
                                'properties'           => [
                                    'nested-key' => [
                                        'type' => 'string',
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
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'properties'           => [
                                'nested_a' => [
                                    'type' => 'string',
                                ],
                                'nested_b' => [
                                    'type'                 => 'object',
                                    'properties'           => [
                                        'nested-key' => [
                                            'type' => 'string',
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
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'        => 'string',
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
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type' => 'string',
                        'enum' => ['value1', 'value2'],
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }
}
