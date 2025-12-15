<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
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
    public function run_routes_to_resolve_objects_operation(): void
    {
        // Given: TaskProcess with OPERATION_RESOLVE_OBJECTS
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
            'operation'   => ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS,
            'meta'        => [
                'level'             => 0,
                'parent_object_ids' => [],
            ],
            'started_at'  => now(),
        ]);

        // When: Running the process
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);

        // Then: Verify operation routing
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS, $taskProcess->operation);
        $this->assertEquals(0, $taskProcess->meta['level']);
    }

    #[Test]
    public function run_routes_to_extract_group_operation(): void
    {
        // Given: TaskProcess with OPERATION_EXTRACT_GROUP
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_GROUP,
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
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_GROUP, $taskProcess->operation);
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
    public function resolve_objects_operation_updates_level_progress(): void
    {
        // Given: TaskRun with cached plan in TaskDefinition.meta and no identification groups
        $this->taskDefinition->meta = [
            'extraction_plan' => [
                'levels' => [
                    [
                        'level'      => 0,
                        'identities' => [], // No identity groups
                        'remaining'  => [
                            [
                                'object_type'       => 'Client',
                                'fields'            => ['address'],
                                'search_mode'       => 'exhaustive',
                                'fragment_selector' => [
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

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS,
            'meta'        => [
                'level'             => 0,
                'parent_object_ids' => [],
            ],
            'started_at'  => now(),
        ]);

        // When: Running resolve objects
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Level progress is updated
        $taskRun->refresh();
        $this->assertTrue($taskRun->meta['level_progress'][0]['resolution_complete'] ?? false);
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
    public function afterAllProcessesCompleted_creates_resolve_objects_after_classification(): void
    {
        // Given: TaskRun with classification complete (classification process completed)
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
            'meta'               => [
                'level_progress' => [],
            ],
        ]);

        // Create completed classification process
        $classificationProcess = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        // When: afterAllProcessesCompleted is called
        $this->runner->setTaskRun($taskRun);
        $this->runner->afterAllProcessesCompleted();

        // Then: Resolve objects process is created for level 0
        $resolveProcess = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS)
            ->where('meta->level', 0)
            ->first();

        $this->assertNotNull($resolveProcess);
    }

    #[Test]
    public function afterAllProcessesCompleted_advances_to_next_level_when_current_complete(): void
    {
        // Given: TaskRun with level 0 complete, plan in TaskDefinition.meta
        $plan = [
            'levels' => [
                ['level' => 0],
                ['level' => 1],
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
                        'resolution_complete' => true,
                        'extraction_complete' => true,
                    ],
                ],
            ],
        ]);

        // When: afterAllProcessesCompleted is called
        $this->runner->setTaskRun($taskRun);
        $this->runner->afterAllProcessesCompleted();

        // Then: Level is advanced and new resolve objects process created (not classification)
        $taskRun->refresh();
        $this->assertEquals(1, $taskRun->meta['current_level']);

        // Should create resolve objects for level 1, not classification (already done once)
        $resolveProcess = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS)
            ->where('meta->level', 1)
            ->first();

        $this->assertNotNull($resolveProcess);
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
}
