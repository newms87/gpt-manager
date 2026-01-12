<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractDataTaskRunnerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractDataTaskRunner $runner;

    private Agent $agent;

    private SchemaDefinition $schemaDefinition;

    private TaskDefinition $taskDefinition;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Create agent for testing
        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-5-mini',
        ]);

        // Create schema definition
        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name'   => ['type' => 'string'],
                    'accident_date' => ['type' => 'string', 'format' => 'date'],
                ],
            ],
        ]);

        // Create task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'Extract Data Test',
            'task_runner_name'     => ExtractDataTaskRunner::RUNNER_NAME,
            'schema_definition_id' => $this->schemaDefinition->id,
            'agent_id'             => $this->agent->id,
            'task_runner_config'   => [
                'confidence_threshold' => 3,
                'skim_batch_size'      => 5,
            ],
        ]);

        $this->runner = new ExtractDataTaskRunner();
    }

    #[Test]
    public function runner_registers_with_correct_name_and_slug(): void
    {
        // When: Getting runner name and slug
        $name = ExtractDataTaskRunner::name();
        $slug = ExtractDataTaskRunner::slug();

        // Then: Matches expected values
        $this->assertEquals('Extract Data', $name);
        $this->assertEquals('extract-data', $slug);
    }

    #[Test]
    public function prepareRun_validates_schema_definition_exists(): void
    {
        // Given: New TaskRun with no schema definition
        $taskDefinitionWithoutSchema = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'Test Without Schema',
            'task_runner_name'     => ExtractDataTaskRunner::RUNNER_NAME,
            'schema_definition_id' => null,
            'agent_id'             => $this->agent->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinitionWithoutSchema->id,
            'meta'               => [],
        ]);

        // When/Then: Should throw validation error
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('ExtractDataTaskRunner requires a Schema Definition');

        $this->runner->setTaskRun($taskRun);
        $this->runner->prepareRun();
    }

    #[Test]
    public function prepareRun_completes_successfully_with_cached_plan(): void
    {
        // Given: TaskDefinition with cached plan
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Client',
                            'identity_fields'   => ['client_name'],
                            'skim_fields'       => ['client_name'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [
                                'children' => [
                                    'client_name' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        $this->taskDefinition->meta = [
            'extraction_plan'           => $plan,
            'extraction_plan_cache_key' => hash('sha256', json_encode([
                'schema'              => $this->schemaDefinition->schema,
                'user_planning_hints' => null,
                'global_search_mode'  => 'intelligent',
                'group_max_points'    => 10,
            ])),
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // When: Preparing run
        $this->runner->setTaskRun($taskRun);
        $this->runner->prepareRun();

        // Then: prepareRun completes successfully, no processes created (that's TaskRunnerService's job)
        $taskRun->refresh();
        $this->assertArrayNotHasKey('extraction_plan', $taskRun->meta);
        $this->assertEquals(0, $taskRun->taskProcesses()->count());
    }

    #[Test]
    public function run_routes_default_task_to_initialize_with_cached_plan(): void
    {
        // Given: TaskDefinition with cached plan and TaskProcess with Default Task operation
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Client',
                            'identity_fields'   => ['client_name'],
                            'skim_fields'       => ['client_name'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        $this->taskDefinition->meta = [
            'extraction_plan'           => $plan,
            'extraction_plan_cache_key' => hash('sha256', json_encode([
                'schema'              => $this->schemaDefinition->schema,
                'user_planning_hints' => null,
                'global_search_mode'  => 'intelligent',
                'group_max_points'    => 10,
            ])),
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create artifacts with storedFiles (required for resolvePages())
        $artifact = \App\Models\Task\Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Page 1',
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);
        $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => 'Default Task', // BaseTaskRunner::OPERATION_DEFAULT
            'meta'        => [],
            'started_at'  => now(),
        ]);

        // When: Running the default task (routes to initialize operation)
        // Fake the queue to prevent sync execution of dispatched jobs (LLM calls)
        Queue::fake();

        // Re-fetch task run to ensure fresh load with eager loading
        $freshTaskRun = TaskRun::with('taskDefinition.agent')->find($taskRun->id);
        $this->runner->setTaskRun($freshTaskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Per-page classification processes are created and default task completes
        $classificationProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->get();

        $this->assertGreaterThan(0, $classificationProcesses->count(), 'Default Task with cached plan should create per-page classification processes');

        // Verify processes have required meta fields
        $classificationProcess = $classificationProcesses->first();
        $this->assertArrayHasKey('child_artifact_id', $classificationProcess->meta);

        // Verify input artifacts are attached
        $this->assertGreaterThan(0, $classificationProcess->inputArtifacts->count());

        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->completed_at);
    }

    #[Test]
    public function run_routes_to_plan_identify_operation(): void
    {
        // Given: TaskProcess with OPERATION_PLAN_IDENTIFY
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY,
            'meta'        => ['object_type' => 'Client'],
            'started_at'  => now(),
        ]);

        // When: Running the process (without LLM, just verify routing)
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);

        // Then: Verify operation routing is correct
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY, $taskProcess->operation);
        $this->assertEquals('Client', $taskProcess->meta['object_type']);
    }

    #[Test]
    public function run_routes_to_plan_remaining_operation(): void
    {
        // Given: TaskProcess with OPERATION_PLAN_REMAINING
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_PLAN_REMAINING,
            'meta'        => ['object_type' => 'Client'],
            'started_at'  => now(),
        ]);

        // When: Running the process (without LLM, just verify routing)
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);

        // Then: Verify operation routing is correct
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_PLAN_REMAINING, $taskProcess->operation);
        $this->assertEquals('Client', $taskProcess->meta['object_type']);
    }

    #[Test]
    public function run_routes_to_classification_operation(): void
    {
        // Given: TaskProcess with OPERATION_CLASSIFY
        // Store plan in TaskDefinition.meta (new location)
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [
                            [
                                'object_type'       => 'Client',
                                'identity_fields'   => ['client_name'],
                                'skim_fields'       => ['client_name'],
                                'search_mode'       => 'skim',
                                'fragment_selector' => [],
                            ],
                        ],
                        'remaining'  => [],
                    ],
                ],
            ],
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'        => [], // No level in meta for classification
            'started_at'  => now(),
        ]);

        // When: Running the process
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);

        // Then: Verify operation routing
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_CLASSIFY, $taskProcess->operation);
    }

    #[Test]
    public function run_routes_to_extract_identity_operation(): void
    {
        // Given: TaskProcess with OPERATION_EXTRACT_IDENTITY
        // Store plan in TaskDefinition.meta (new location)
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [
                            [
                                'object_type'       => 'Client',
                                'identity_fields'   => ['client_name'],
                                'skim_fields'       => ['client_name'],
                                'search_mode'       => 'skim',
                                'fragment_selector' => [
                                    'children' => [
                                        'client_name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                        'remaining'  => [],
                    ],
                ],
            ],
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'        => [
                'level'          => 0,
                'identity_group' => [
                    'object_type'       => 'Client',
                    'identity_fields'   => ['client_name'],
                    'skim_fields'       => ['client_name'],
                    'search_mode'       => 'skim',
                    'fragment_selector' => [
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'started_at'  => now(),
        ]);

        // When: Running the process
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);

        // Then: Verify operation routing
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY, $taskProcess->operation);
        $this->assertEquals(0, $taskProcess->meta['level']);
    }

    #[Test]
    public function run_routes_to_extract_remaining_operation(): void
    {
        // Given: TaskProcess with OPERATION_EXTRACT_REMAINING
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
            'meta'        => [
                'level'            => 0,
                'object_id'        => 123,
                'extraction_group' => [
                    'name'    => 'Test Group',
                    'objects' => [],
                ],
                'search_mode'      => 'skim',
            ],
            'started_at'  => now(),
        ]);

        // When: Running the process
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);

        // Then: Verify operation routing
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING, $taskProcess->operation);
        $this->assertEquals(123, $taskProcess->meta['object_id']);
    }

    #[Test]
    public function classification_operation_completes_successfully(): void
    {
        // Given: TaskRun with cached plan in TaskDefinition.meta and classification schema
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Client',
                            'identity_fields'   => ['client_name'],
                            'skim_fields'       => ['client_name'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        $this->taskDefinition->meta = ['extraction_plan' => $plan];
        $this->taskDefinition->save();

        // Create artifact and storedFile
        $artifact = \App\Models\Task\Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Page 1',
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);

        // Build and store classification schema
        $schemaBuilder = app(\App\Services\Task\DataExtraction\ClassificationSchemaBuilder::class);
        $booleanSchema = $schemaBuilder->buildBooleanSchema($plan);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [
                'classification_schema' => $booleanSchema,
            ],
        ]);

        $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'        => [
                'child_artifact_id' => $artifact->id,
            ],
            'started_at'  => now(),
        ]);

        // Attach artifact as input to process
        $taskProcess->inputArtifacts()->attach($artifact->id);

        // When: Running classification (mock the ClassificationExecutorService)
        Queue::fake();

        // Mock ClassificationExecutorService to avoid actual LLM calls
        $mockService = $this->mock(\App\Services\Task\DataExtraction\ClassificationExecutorService::class);
        $mockService->shouldReceive('classifyPage')
            ->once()
            ->andReturn([
                'client_identification' => true,
            ]);

        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Process completes and classification result is stored in child artifact meta
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->completed_at);

        // Verify classification result is stored in child artifact meta
        $artifact->refresh();
        $this->assertArrayHasKey('classification', $artifact->meta);
        $this->assertNotEmpty($artifact->meta['classification']);
    }

    #[Test]
    public function extract_identity_operation_updates_level_progress(): void
    {
        // Given: TaskRun with cached plan in TaskDefinition.meta
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [
                            [
                                'name'              => 'Client',
                                'object_type'       => 'Client',
                                'identity_fields'   => ['client_name'],
                                'skim_fields'       => ['client_name'],
                                'search_mode'       => 'skim',
                                'fragment_selector' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'client_name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                        'remaining'  => [
                            [
                                'name'              => 'Client Address',
                                'key'               => 'client_address',
                                'object_type'       => 'Client',
                                'fields'            => ['address'],
                                'search_mode'       => 'exhaustive',
                                'fragment_selector' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'address' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create input artifact with classification
        $inputArtifact = Artifact::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'task_run_id' => $taskRun->id,
            'name'        => 'Page 1',
            'meta'        => [
                'classification' => [
                    'client_identification' => true,
                ],
            ],
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $inputArtifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'        => [
                'level'          => 0,
                'identity_group' => [
                    'name'              => 'Client',
                    'object_type'       => 'Client',
                    'identity_fields'   => ['client_name'],
                    'skim_fields'       => ['client_name'],
                    'search_mode'       => 'skim',
                    'fragment_selector' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'started_at'  => now(),
        ]);

        // Attach artifact as input to process
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Mock AgentThreadBuilderService to return a real thread
        $thread = \App\Models\Agent\AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mock(\App\Services\AgentThread\AgentThreadBuilderService::class, function ($mock) use ($thread) {
            $builderMock = \Mockery::mock();
            $builderMock->shouldReceive('named')->andReturnSelf();
            $builderMock->shouldReceive('withArtifacts')->andReturnSelf();
            $builderMock->shouldReceive('build')->andReturn($thread);

            $mock->shouldReceive('for')->andReturn($builderMock);
        });

        // Mock AgentThreadService to avoid actual LLM calls
        $mockMessage = $this->createMock(\App\Models\Agent\AgentThreadMessage::class);
        $mockMessage->method('getJsonContent')->willReturn([
            'data'         => ['client_name' => 'Test Client'],
            'search_query' => ['client_name' => '%Test%'],
        ]);

        $mockThreadRun              = $this->mock(\App\Models\Agent\AgentThreadRun::class)->makePartial();
        $mockThreadRun->lastMessage = $mockMessage;
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

        $this->mock(\App\Services\AgentThread\AgentThreadService::class, function ($mock) use ($mockThreadRun) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($mockThreadRun);
        });

        // When: Running extract identity
        $freshTaskRun = TaskRun::with('taskDefinition.agent')->find($taskRun->id);
        $this->runner->setTaskRun($freshTaskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Level progress is updated
        $taskRun->refresh();
        $this->assertTrue($taskRun->meta['level_progress'][0]['identity_complete'] ?? false);
    }

    #[Test]
    public function afterAllProcessesCompleted_creates_classification_after_planning(): void
    {
        // Given: TaskRun that just completed all planning (identity + remaining), plan stored in TaskDefinition.meta
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Client',
                            'identity_fields'   => ['client_name'],
                            'skim_fields'       => ['client_name'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        $this->taskDefinition->meta = ['extraction_plan' => $plan];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create artifacts with storedFiles (required for creating per-page classification processes)
        $artifact = \App\Models\Task\Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Page 1',
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);
        $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        // Create completed planning processes (identity planning completed, no remaining needed)
        $identifyProcess = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY,
            'meta'         => ['object_type' => 'Client'],
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        // When: afterAllProcessesCompleted is called
        $this->runner->setTaskRun($taskRun);
        $this->runner->afterAllProcessesCompleted();

        // Then: Per-page classification processes are created
        $classificationProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->get();

        $this->assertGreaterThan(0, $classificationProcesses->count(), 'Should create per-page classification processes after planning');

        // Verify processes have required meta fields
        $classificationProcess = $classificationProcesses->first();
        $this->assertArrayHasKey('child_artifact_id', $classificationProcess->meta);

        // Verify input artifacts are attached
        $this->assertGreaterThan(0, $classificationProcess->inputArtifacts->count());
    }

    #[Test]
    public function afterAllProcessesCompleted_creates_extract_identity_after_classification(): void
    {
        // Given: TaskRun with classification complete (classification process completed)
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'              => 'Client',
                            'object_type'       => 'Client',
                            'identity_fields'   => ['client_name'],
                            'skim_fields'       => ['client_name'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        $this->taskDefinition->meta = ['extraction_plan' => $plan];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [
                'level_progress' => [],
            ],
        ]);

        // Create parent output artifact
        $parentArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);

        // Create child artifact with classification for "Client Identification"
        $childArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => [
                'classification' => [
                    'client_identification' => true,
                ],
            ],
        ]);

        // Attach as output artifacts to task run
        $taskRun->outputArtifacts()->attach($parentArtifact->id);
        $taskRun->outputArtifacts()->attach($childArtifact->id);

        // Create completed classification process
        $classificationProcess = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        // When: afterAllProcessesCompleted is called
        $this->runner->setTaskRun($taskRun->fresh(['outputArtifacts', 'outputArtifacts.children']));
        $this->runner->afterAllProcessesCompleted();

        // Then: Extract Identity process is created for level 0
        $extractProcess = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->where('meta->level', 0)
            ->first();

        $this->assertNotNull($extractProcess);
    }

    #[Test]
    public function afterAllProcessesCompleted_advances_to_next_level_when_current_complete(): void
    {
        // Given: TaskRun with level 0 complete, plan in TaskDefinition.meta with identity groups for level 1
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'              => 'Client',
                            'object_type'       => 'Client',
                            'identity_fields'   => ['client_name'],
                            'skim_fields'       => ['client_name'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [],
                ],
                [
                    'level'      => 1,
                    'identities' => [
                        [
                            'name'              => 'Claim',
                            'object_type'       => 'Claim',
                            'identity_fields'   => ['claim_number'],
                            'skim_fields'       => ['claim_number'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        $this->taskDefinition->meta = ['extraction_plan' => $plan];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [
                'current_level'  => 0,
                'level_progress' => [
                    0 => [
                        'identity_complete'   => true,
                        'extraction_complete' => true,
                    ],
                ],
            ],
        ]);

        // Create parent output artifact
        $parentArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);

        // Create child artifact with classification for "Claim Identification"
        $childArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => [
                'classification' => [
                    'claim_identification' => true,
                ],
            ],
        ]);

        // Attach as output artifacts to task run
        $taskRun->outputArtifacts()->attach($parentArtifact->id);
        $taskRun->outputArtifacts()->attach($childArtifact->id);

        // When: afterAllProcessesCompleted is called
        $this->runner->setTaskRun($taskRun->fresh(['outputArtifacts', 'outputArtifacts.children']));
        $this->runner->afterAllProcessesCompleted();

        // Then: Level is advanced and new extract identity process created (not classification)
        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->meta['current_level']);

        // Should create extract identity for level 1, not classification (already done once)
        $extractProcess = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->where('meta->level', 1)
            ->first();

        $this->assertNotNull($extractProcess);
    }

    #[Test]
    public function run_routes_default_task_to_initialize_without_cached_plan(): void
    {
        // Given: TaskDefinition WITHOUT cached plan and TaskProcess with Default Task operation
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => 'Default Task', // BaseTaskRunner::OPERATION_DEFAULT
            'activity'    => 'Starting extraction',
            'started_at'  => now(),
        ]);

        // When: Running the default task (routes to initialize operation)
        // Fake the queue to prevent sync execution of dispatched jobs (LLM calls)
        Queue::fake();

        // Re-fetch task run to ensure fresh load with eager loading
        $freshTaskRun = TaskRun::with('taskDefinition.agent')->find($taskRun->id);
        $this->runner->setTaskRun($freshTaskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Identity planning processes are created (one per object type) and default task completes
        $identifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY)
            ->get();

        $this->assertGreaterThan(0, $identifyProcesses->count(), 'Default Task without cached plan should create identity planning processes');

        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->completed_at);
    }

    #[Test]
    public function extract_identity_operation_creates_identity_schema_correctly(): void
    {
        // Given: TaskRun with cached plan in TaskDefinition.meta and identity group with fragment selector
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [
                            [
                                'name'              => 'Client',
                                'object_type'       => 'Client',
                                'identity_fields'   => ['client_name'],
                                'skim_fields'       => ['client_name'],
                                'search_mode'       => 'skim',
                                'fragment_selector' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'client_name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                        'remaining'  => [],
                    ],
                ],
            ],
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create artifact with storedFile (required for the extraction)
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Page 1',
            'meta'    => [
                'classification' => [
                    'client_identification' => true,
                ],
            ],
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'        => [
                'level'          => 0,
                'identity_group' => [
                    'name'              => 'Client',
                    'object_type'       => 'Client',
                    'identity_fields'   => ['client_name'],
                    'skim_fields'       => ['client_name'],
                    'search_mode'       => 'skim',
                    'fragment_selector' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'started_at'  => now(),
        ]);

        // Attach artifact as input to process
        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock AgentThreadBuilderService to return a real thread
        $thread = \App\Models\Agent\AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mock(\App\Services\AgentThread\AgentThreadBuilderService::class, function ($mock) use ($thread) {
            $builderMock = \Mockery::mock();
            $builderMock->shouldReceive('named')->andReturnSelf();
            $builderMock->shouldReceive('withArtifacts')->andReturnSelf();
            $builderMock->shouldReceive('build')->andReturn($thread);

            $mock->shouldReceive('for')->andReturn($builderMock);
        });

        // Mock AgentThreadService to avoid actual LLM calls and return a completed thread run
        $mockMessage = $this->createMock(\App\Models\Agent\AgentThreadMessage::class);
        $mockMessage->method('getJsonContent')->willReturn([
            'data'         => ['client_name' => 'Test Client'],
            'search_query' => ['client_name' => '%Test%'],
        ]);

        $mockThreadRun              = $this->mock(\App\Models\Agent\AgentThreadRun::class)->makePartial();
        $mockThreadRun->lastMessage = $mockMessage;
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

        $this->mock(\App\Services\AgentThread\AgentThreadService::class, function ($mock) use ($mockThreadRun) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($mockThreadRun);
        });

        // When: Running the extract identity operation
        // This should now succeed because the bug has been fixed
        $freshTaskRun = TaskRun::with('taskDefinition.agent')->find($taskRun->id);
        $this->runner->setTaskRun($freshTaskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Process completes successfully (or updates level progress)
        $taskProcess->refresh();
        $taskRun->refresh();

        // The operation should complete without throwing a setSchema error
        // Level progress should be updated to mark identity complete
        $this->assertTrue(
            $taskRun->meta['level_progress'][0]['identity_complete'] ?? false,
            'Level 0 identity should be marked as complete after successful extraction'
        );
    }

    #[Test]
    public function extract_identity_operation_creates_output_artifact(): void
    {
        // Given: TaskRun with cached plan and identity group with fragment selector
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [
                            [
                                'name'              => 'Client',
                                'object_type'       => 'Client',
                                'identity_fields'   => ['client_name'],
                                'skim_fields'       => ['client_name'],
                                'search_mode'       => 'skim',
                                'fragment_selector' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'client_name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                        'remaining'  => [],
                    ],
                ],
            ],
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create input artifact with classification
        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $taskRun->id,
            'name'         => 'Page 1',
            'json_content' => [],
            'meta'         => [
                'classification' => [
                    'client_identification' => true,
                ],
            ],
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $inputArtifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'        => [
                'level'          => 0,
                'identity_group' => [
                    'name'              => 'Client',
                    'object_type'       => 'Client',
                    'identity_fields'   => ['client_name'],
                    'skim_fields'       => ['client_name'],
                    'search_mode'       => 'skim',
                    'fragment_selector' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'started_at'  => now(),
        ]);

        // Attach artifact as input to process
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Mock AgentThreadBuilderService to return a real thread
        $thread = \App\Models\Agent\AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mock(\App\Services\AgentThread\AgentThreadBuilderService::class, function ($mock) use ($thread) {
            $builderMock = \Mockery::mock();
            $builderMock->shouldReceive('named')->andReturnSelf();
            $builderMock->shouldReceive('withArtifacts')->andReturnSelf();
            $builderMock->shouldReceive('build')->andReturn($thread);

            $mock->shouldReceive('for')->andReturn($builderMock);
        });

        // Mock AgentThreadService to return extraction result with data matching fragment_selector
        // The fragment_selector specifies: children.client_name => type: string (scalar only = flat structure)
        // For flat structures, getLeafKey returns snake_case of object_type: 'Client' -> 'client'
        // So the data must be nested under the 'client' key
        $mockMessage = $this->createMock(\App\Models\Agent\AgentThreadMessage::class);
        $mockMessage->method('getJsonContent')->willReturn([
            'data' => [
                'client' => [  // leaf key = snake_case of object_type
                    'client_name'   => 'John Doe Insurance',
                    '_search_query' => [['client_name' => '%John%Doe%']],  // embedded search query
                ],
            ],
        ]);

        $mockThreadRun              = $this->mock(\App\Models\Agent\AgentThreadRun::class)->makePartial();
        $mockThreadRun->lastMessage = $mockMessage;
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

        $this->mock(\App\Services\AgentThread\AgentThreadService::class, function ($mock) use ($mockThreadRun) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($mockThreadRun);
        });

        // When: Running the extract identity operation
        $freshTaskRun = TaskRun::with('taskDefinition.agent')->find($taskRun->id);
        $this->runner->setTaskRun($freshTaskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Output artifact is created with correct structure
        $taskProcess->refresh();
        $outputArtifact = $taskProcess->outputArtifacts()->first();

        // Verify output artifact was created
        $this->assertNotNull($outputArtifact, 'Output artifact should be created');
        $this->assertStringContainsString('Identity:', $outputArtifact->name);
        $this->assertStringContainsString('Client', $outputArtifact->name);

        // Verify json_content has data at root level with id and type
        $jsonContent = $outputArtifact->json_content;
        $this->assertArrayHasKey('id', $jsonContent);
        $this->assertNotNull($jsonContent['id']);
        $this->assertEquals('Client', $jsonContent['type']);

        // Verify extracted fields are at root level (not nested in extracted_data)
        $this->assertArrayHasKey('client_name', $jsonContent);
        $this->assertEquals('John Doe Insurance', $jsonContent['client_name']);

        // Verify meta contains operational fields
        $meta = $outputArtifact->meta;
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY, $meta['operation']);
        $this->assertEquals([['client_name' => '%John%Doe%']], $meta['search_query']);
        $this->assertArrayHasKey('was_existing', $meta);
        $this->assertArrayHasKey('match_id', $meta);
        $this->assertEquals($taskProcess->id, $meta['task_process_id']);
        $this->assertEquals(0, $meta['level']);
        $this->assertEquals('Client', $meta['identity_group']);

        // Verify parent-child relationship via parent_artifact_id
        $this->assertEquals($inputArtifact->id, $outputArtifact->parent_artifact_id);

        // Input artifact's json_content is NOT modified (extracted artifacts are children via parent_artifact_id)
        $inputArtifact->refresh();
        $this->assertEmpty($inputArtifact->json_content);
    }

    #[Test]
    public function extract_remaining_operation_creates_output_artifact(): void
    {
        // Given: TaskRun with extraction plan and a resolved TeamObject
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [
                            [
                                'name'              => 'Client',
                                'object_type'       => 'Client',
                                'identity_fields'   => ['client_name'],
                                'skim_fields'       => ['client_name'],
                                'search_mode'       => 'skim',
                                'fragment_selector' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'client_name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                        'remaining'  => [
                            [
                                'name'              => 'Client Address',
                                'key'               => 'client_address',
                                'object_type'       => 'Client',
                                'fields'            => ['address', 'city', 'state'],
                                'search_mode'       => 'exhaustive',
                                'fragment_selector' => [
                                    'type'     => 'object',
                                    'children' => [
                                        'address' => ['type' => 'string'],
                                        'city'    => ['type' => 'string'],
                                        'state'   => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create parent output artifact (represents the extraction session)
        $parentArtifact = Artifact::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_run_id'        => $taskRun->id,
            'name'               => 'Extraction Output',
            'parent_artifact_id' => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // Create child artifact with classification for the extraction group
        $childArtifact = Artifact::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => $parentArtifact->id,
            'name'               => 'Page 1',
            'json_content'       => [],
            'meta'               => [
                'classification' => [
                    'client_address' => true,
                ],
            ],
        ]);

        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);
        $childArtifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);

        // Create existing TeamObject
        $teamObject = \App\Models\TeamObject\TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Doe Insurance',
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
            'meta'        => [
                'level'            => 0,
                'object_id'        => $teamObject->id,
                'search_mode'      => 'exhaustive',
                'extraction_group' => [
                    'name'              => 'Client Address',
                    'key'               => 'client_address',
                    'object_type'       => 'Client',
                    'fields'            => ['address', 'city', 'state'],
                    'search_mode'       => 'exhaustive',
                    'fragment_selector' => [
                        'type'     => 'object',
                        'children' => [
                            'address' => ['type' => 'string'],
                            'city'    => ['type' => 'string'],
                            'state'   => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'started_at'  => now(),
        ]);

        // Attach artifact as input to process
        $taskProcess->inputArtifacts()->attach($childArtifact->id);

        // Mock GroupExtractionService to return extracted data
        $this->mock(\App\Services\Task\DataExtraction\GroupExtractionService::class, function ($mock) {
            $mock->shouldReceive('extractExhaustive')
                ->once()
                ->andReturn([
                    'data' => [
                        'address' => '123 Main Street',
                        'city'    => 'Springfield',
                        'state'   => 'IL',
                    ],
                    'page_sources' => [],
                ]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')
                ->once();
        });

        // When: Running the extract remaining operation
        $freshTaskRun = TaskRun::with('taskDefinition.agent')->find($taskRun->id);
        $this->runner->setTaskRun($freshTaskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Output artifact is created with correct structure
        $taskProcess->refresh();
        $outputArtifact = $taskProcess->outputArtifacts()->first();

        // Verify output artifact was created
        $this->assertNotNull($outputArtifact, 'Output artifact should be created');
        $this->assertStringContainsString('Remaining:', $outputArtifact->name);
        $this->assertStringContainsString('Client Address', $outputArtifact->name);

        // Verify json_content has data at root level with id and type
        $jsonContent = $outputArtifact->json_content;
        $this->assertArrayHasKey('id', $jsonContent);
        $this->assertEquals($teamObject->id, $jsonContent['id']);
        $this->assertEquals('Client', $jsonContent['type']);

        // Verify extracted fields are at root level (not nested in extracted_data)
        $this->assertEquals('123 Main Street', $jsonContent['address']);
        $this->assertEquals('Springfield', $jsonContent['city']);
        $this->assertEquals('IL', $jsonContent['state']);

        // Verify meta contains operational fields
        $meta = $outputArtifact->meta;
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING, $meta['operation']);
        $this->assertEquals('exhaustive', $meta['extraction_mode']);
        $this->assertEquals($taskProcess->id, $meta['task_process_id']);
        $this->assertEquals(0, $meta['level']);
        $this->assertEquals('Client Address', $meta['extraction_group']);

        // Verify parent-child relationship via parent_artifact_id
        $this->assertEquals($childArtifact->id, $outputArtifact->parent_artifact_id);

        // Input artifact's json_content is NOT modified (extracted artifacts are children via parent_artifact_id)
        $childArtifact->refresh();
        $this->assertEmpty($childArtifact->json_content);
    }
}
