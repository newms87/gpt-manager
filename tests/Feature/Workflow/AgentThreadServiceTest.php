<?php

namespace Tests\Feature\Workflow;

use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
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
        $thread      = Thread::factory()->create(['agent_id' => $agent->id]);
        $thread->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Test message']);

        $service = new AgentThreadService();

        // When
        $threadRun = $service->run($thread);

        // Then
        $this->assertEquals(ThreadRun::STATUS_RUNNING, $threadRun->status);
        $this->assertEquals($temperature, $threadRun->temperature);
        $this->assertEquals($agent->tools, $threadRun->tools);
        $this->assertEquals(Agent::RESPONSE_FORMAT_JSON_OBJECT, $threadRun->response_format);
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
        $agent   = Agent::factory()->create(['response_format' => Agent::RESPONSE_FORMAT_TEXT]);
        $thread  = Thread::factory()->withMessages(1)->create(['agent_id' => $agent->id]);
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
                        'type'                 => 'object',
                        'properties'           => [
                            'nested_a' => [
                                'type' => ['string', 'null'],
                            ],
                            'nested_b' => [
                                'type'                 => 'object',
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
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'properties'           => [
                                'nested_a' => [
                                    'type' => ['string', 'null'],
                                ],
                                'nested_b' => [
                                    'type'                 => 'object',
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

    public function test_formatResponseSchemaForAgent_onlySelectedPropertyIsReturned(): void
    {
        // Given
        $schema       = [
            'type'        => 'object',
            'title'       => 'Person',
            'description' => 'The important person',
            'properties'  => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'Name of the person',
                ],
                'dob'  => [
                    'type' => 'string',
                ],
            ],
        ];
        $subSelection = [
            'type'     => 'object',
            'children' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchemaForAgent($agent);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'description'          => 'The important person',
                'properties'           => [
                    'name' => [
                        'type'        => 'string',
                        'description' => 'Name of the person',
                    ],
                ],
                'required'             => ['name'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchemaForAgent_onlySelectedObjectsAreReturned(): void
    {
        // Given
        $schema       = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'    => [
                    'type' => 'string',
                ],
                'dob'     => [
                    'type' => 'string',
                ],
                'job'     => [
                    'type'       => 'object',
                    'title'      => 'Job',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'address' => [
                    'type'       => 'object',
                    'title'      => 'Address',
                    'properties' => [
                        'street' => [
                            'type' => 'string',
                        ],
                        'city'   => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
        $subSelection = [
            'type'     => 'object',
            'children' => [
                'address' => [
                    'type'     => 'object',
                    'children' => [
                        'city' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchemaForAgent($agent);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'address' => [
                        'type'                 => 'object',
                        'title'                => 'Address',
                        'properties'           => [
                            'city' => [
                                'type' => ['string', 'null'],
                            ],
                        ],
                        'required'             => ['city'],
                        'additionalProperties' => false,
                    ],
                ],
                'required'             => ['address'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchemaForAgent_selectedObjectSkippedWhenNoPropertiesSelected(): void
    {
        // Given
        $schema       = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'    => [
                    'type' => 'string',
                ],
                'address' => [
                    'type'       => 'object',
                    'title'      => 'Address',
                    'properties' => [
                        'street' => [
                            'type' => 'string',
                        ],
                        'city'   => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
        $subSelection = [
            'type'     => 'object',
            'children' => [
                'name'    => [
                    'type' => 'string',
                ],
                'address' => [
                    'type' => 'object',
                ],
            ],
        ];
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchemaForAgent($agent);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'name' => [
                        'type' => 'string',
                    ],
                ],
                'required'             => ['name'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatResponseSchemaForAgent_onlySelectedArraysAreReturned(): void
    {
        // Given
        $schema       = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'      => [
                    'type' => 'string',
                ],
                'dob'       => [
                    'type' => 'string',
                ],
                'job'       => [
                    'type'       => 'object',
                    'title'      => 'Job',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'addresses' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'title'      => 'Address',
                        'properties' => [
                            'street' => [
                                'type' => 'string',
                            ],
                            'city'   => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $subSelection = [
            'type'     => 'object',
            'children' => [
                'addresses' => [
                    'type'     => 'array',
                    'children' => [
                        'city' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(AgentThreadService::class)->formatResponseSchemaForAgent($agent);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'addresses' => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'title'                => 'Address',
                            'properties'           => [
                                'city' => [
                                    'type' => ['string', 'null'],
                                ],
                            ],
                            'required'             => ['city'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required'             => ['addresses'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }
}
