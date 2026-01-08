<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Task\DataExtraction\DuplicateRecordResolver;
use App\Services\Task\DataExtraction\ExtractionArtifactBuilder;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\DataExtraction\IdentityExtractionService;
use App\Services\Task\DataExtraction\ResolutionResult;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class IdentityExtractionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private IdentityExtractionService $service;

    private Agent $agent;

    private SchemaDefinition $schemaDefinition;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(IdentityExtractionService::class);

        // Set up common test fixtures
        $this->agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                    'client_id'   => ['type' => 'string'],
                ],
            ],
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $this->agent->id,
            'schema_definition_id' => $this->schemaDefinition->id,
            'task_runner_config'   => [
                'extraction_timeout' => 60,
            ],
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    // =========================================================================
    // execute() - Input validation tests
    // =========================================================================

    #[Test]
    public function execute_throws_exception_when_no_input_artifacts(): void
    {
        // Given: TaskProcess with no input artifacts
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        // No artifacts attached

        $identityGroup = [
            'name'            => 'Client',
            'object_type'     => 'Client',
            'identity_fields' => ['client_name'],
        ];

        // Then: Expect exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has no input artifacts');

        // When: Executing identity extraction - should throw
        $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );
    }

    // =========================================================================
    // execute() - TeamObject creation tests
    // =========================================================================

    #[Test]
    public function execute_creates_team_object_when_no_duplicate_found(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Mock AgentThreadBuilderService
        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock AgentThreadService with new schema format (leaf key with embedded _search_query)
        $this->mockAgentThreadService([
            'data' => [
                'client' => [
                    'client_name'   => 'New Client Corp',
                    '_search_query' => ['client_name' => '%New%Client%'],
                ],
            ],
        ]);

        // Mock DuplicateRecordResolver to return no match
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Returns a new TeamObject
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals('Client', $result->type);
    }

    #[Test]
    public function execute_uses_existing_team_object_when_duplicate_found(): void
    {
        // Given: Existing TeamObject
        $existingTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Existing Client',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Mock AgentThreadBuilderService
        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock AgentThreadService with new schema format
        $this->mockAgentThreadService([
            'data' => [
                'client' => [
                    'client_name'   => 'Existing Client',
                    '_search_query' => ['client_name' => '%Existing%Client%'],
                ],
            ],
        ]);

        // Mock DuplicateRecordResolver to return existing object via quick match
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) use ($existingTeamObject) {
            $mock->shouldReceive('findCandidates')->andReturn(collect([$existingTeamObject]));
            $mock->shouldReceive('quickMatchCheck')->andReturn($existingTeamObject);
        });

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) use ($existingTeamObject) {
            $mock->shouldReceive('storeResolvedObjectId')
                ->with(Mockery::any(), 'Client', $existingTeamObject->id, 0)
                ->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($existingTeamObject) {
            $mock->shouldReceive('buildIdentityArtifact')
                ->with(
                    Mockery::any(),
                    Mockery::any(),
                    Mockery::on(fn($obj) => $obj->id === $existingTeamObject->id),
                    Mockery::any(),
                    Mockery::any(),
                    0,
                    $existingTeamObject->id
                )
                ->once()
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Returns the existing TeamObject
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals($existingTeamObject->id, $result->id);
    }

    #[Test]
    public function execute_stores_resolved_object_id_in_orchestrator(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Demand',
            'object_type'       => 'Demand',
            'identity_fields'   => ['demand_id'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'demand' => [
                        'type'     => 'object',
                        'children' => [
                            'demand_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Mock dependencies
        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data' => [
                'demand' => [
                    'demand_id'     => 'DEM-001',
                    '_search_query' => ['demand_id' => '%DEM-001%'],
                ],
            ],
        ]);

        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        // Mock ExtractionProcessOrchestrator and verify it's called correctly
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')
                ->with(
                    Mockery::type(TaskRun::class),
                    'Demand',
                    Mockery::type('int'),
                    1  // level
                )
                ->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When: Executing identity extraction at level 1
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 1
        );

        // Then: TeamObject was created and stored
        $this->assertInstanceOf(TeamObject::class, $result);
    }

    #[Test]
    public function execute_builds_identity_artifact(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data' => [
                'client' => [
                    'client_name'   => 'Test Client',
                    '_search_query' => ['client_name' => '%Test%'],
                ],
            ],
        ]);

        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        // Mock and verify ExtractionArtifactBuilder is called
        $builtArtifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($builtArtifact) {
            $mock->shouldReceive('buildIdentityArtifact')
                ->with(
                    Mockery::type(TaskRun::class),
                    Mockery::type(TaskProcess::class),
                    Mockery::type(TeamObject::class),
                    Mockery::on(fn($g) => $g['object_type'] === 'Client'),
                    Mockery::on(fn($r) => isset($r['data']['client_name'])),
                    0,
                    null  // matchId
                )
                ->once()
                ->andReturn($builtArtifact);
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Returns a TeamObject
        $this->assertInstanceOf(TeamObject::class, $result);
    }

    // =========================================================================
    // execute() - LLM resolution tests
    // =========================================================================

    #[Test]
    public function execute_uses_llm_resolution_when_quick_match_fails(): void
    {
        // Given: Existing TeamObject that doesn't exactly match
        $existingTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John W. Smith',  // Different from extracted "John Smith"
        ]);

        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data' => [
                'client' => [
                    'client_name'   => 'John Smith',
                    '_search_query' => ['client_name' => '%John%Smith%'],
                ],
            ],
        ]);

        // Mock DuplicateRecordResolver to use LLM resolution
        $resolutionResult = new ResolutionResult(
            isDuplicate: true,
            existingObjectId: $existingTeamObject->id,
            existingObject: $existingTeamObject,
            explanation: 'Names are similar variations',
            confidence: 0.85
        );

        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) use ($existingTeamObject, $resolutionResult) {
            $mock->shouldReceive('findCandidates')->andReturn(collect([$existingTeamObject]));
            $mock->shouldReceive('quickMatchCheck')->andReturn(null);  // No exact match
            $mock->shouldReceive('resolveDuplicate')->andReturn($resolutionResult);
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Returns the existing TeamObject resolved via LLM
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals($existingTeamObject->id, $result->id);
    }

    #[Test]
    public function execute_returns_null_when_extraction_fails(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',
                        'children' => [
                            'client_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock AgentThreadService to return failed run
        $mockThreadRun = $this->mock(AgentThreadRun::class)->makePartial();
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(false);
        $mockThreadRun->error = 'Timeout';

        $this->mock(AgentThreadService::class, function (MockInterface $mock) use ($mockThreadRun) {
            $mock->shouldReceive('withResponseFormat')->andReturnSelf();
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')->andReturn($mockThreadRun);
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Returns null
        $this->assertNull($result);
    }

    #[Test]
    public function execute_handles_parent_object_id(): void
    {
        // Given: Parent object exists (Demand is parent of Provider)
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
        ]);

        // Input artifact with resolved_objects containing the parent
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Demand' => [$parentObject->id],
                ],
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector: getNestingKeys returns ['demand', 'provider']
        // getParentType returns second-to-last = 'demand' => 'Demand' (title case)
        // This represents: demand > provider > {scalar fields} (2-level hierarchy)
        $identityGroup = [
            'name'              => 'Provider',
            'object_type'       => 'Provider',
            'identity_fields'   => ['provider_name'],
            'fragment_selector' => [
                'children' => [
                    'demand' => [
                        'type'     => 'object',
                        'children' => [
                            'provider' => [
                                'type'     => 'array',
                                'children' => [
                                    'provider_name' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        // New schema format: leaf key with array of items, each having embedded _search_query
        $this->mockAgentThreadService([
            'data' => [
                'provider' => [
                    [
                        'provider_name' => 'Some Provider',
                        '_search_query' => ['provider_name' => '%Some%Provider%'],
                    ],
                ],
            ],
        ]);

        // Mock DuplicateRecordResolver to verify parent scope is passed
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) use ($parentObject) {
            $mock->shouldReceive('findCandidates')
                ->with('Provider', Mockery::any(), $parentObject->id, Mockery::any())
                ->andReturn(collect());
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When: Executing identity extraction (parent is now resolved from artifacts)
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 1
        );

        // Then: Returns a TeamObject
        $this->assertInstanceOf(TeamObject::class, $result);
    }

    // =========================================================================
    // execute() - Empty name handling tests
    // =========================================================================

    #[Test]
    public function execute_returns_null_when_no_identity_data_found(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Fragment selector with proper object structure
        $identityGroup = [
            'name'              => 'Demand',
            'object_type'       => 'Demand',
            'identity_fields'   => ['name', 'accident_date'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'demand' => [
                        'type'     => 'object',
                        'children' => [
                            'name'          => ['type' => 'string'],
                            'accident_date' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock LLM returns empty strings - no data found scenario
        $this->mockAgentThreadService([
            'data' => [
                'demand' => [
                    'name'          => '',
                    'accident_date' => '',
                    '_search_query' => ['name' => '%%'],
                ],
            ],
        ]);

        // When: Executing identity extraction with empty data from LLM
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Returns null (no data found, no TeamObject created)
        $this->assertNull($result);
    }

    // =========================================================================
    // execute() - Array-type identity extraction tests
    // =========================================================================

    #[Test]
    public function execute_creates_multiple_team_objects_for_array_type_identity(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Identity group with array-type at root level (simplest case)
        // The key is that 'diagnosis' has type: 'array' - multiple diagnoses per document
        $identityGroup = [
            'name'              => 'Diagnosis',
            'object_type'       => 'Diagnosis',
            'identity_fields'   => ['name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'diagnosis' => [
                        'type'     => 'array',  // <-- Array type - multiple items!
                        'children' => [
                            'name'        => ['type' => 'string'],
                            'date'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock LLM returns 3 diagnoses with embedded _search_query in each item
        $this->mockAgentThreadService([
            'data' => [
                'diagnosis' => [
                    ['name' => 'G44.319', 'date' => '2024-01-15', 'description' => 'Headache', '_search_query' => ['name' => '%G44%']],
                    ['name' => 'M54.2', 'date' => '2024-01-15', 'description' => 'Cervicalgia', '_search_query' => ['name' => '%M54%']],
                    ['name' => 'S13.4', 'date' => '2024-01-15', 'description' => 'Sprain', '_search_query' => ['name' => '%S13%']],
                ],
            ],
        ]);

        // Mock DuplicateRecordResolver - no duplicates found for any
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        // Track how many times storeResolvedObjectId is called
        $storedObjectIds = [];
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) use (&$storedObjectIds) {
            $mock->shouldReceive('storeResolvedObjectId')
                ->andReturnUsing(function ($taskRun, $objectType, $objectId, $level) use (&$storedObjectIds) {
                    $storedObjectIds[] = $objectId;
                });
        });

        // Track how many artifacts are built
        $builtArtifacts = [];
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use (&$builtArtifacts) {
            $mock->shouldReceive('buildIdentityArtifact')
                ->andReturnUsing(function () use (&$builtArtifacts) {
                    $artifact         = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
                    $builtArtifacts[] = $artifact;

                    return $artifact;
                });
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Should have created 3 TeamObjects (one for each diagnosis)
        $createdTeamObjects = TeamObject::where('team_id', $this->user->currentTeam->id)
            ->where('type', 'Diagnosis')
            ->get();

        $this->assertCount(
            3,
            $createdTeamObjects,
            'Expected 3 TeamObjects to be created for 3 diagnoses, but got ' . $createdTeamObjects->count()
        );

        // Verify all 3 diagnoses were stored
        $this->assertCount(3, $storedObjectIds, 'Expected 3 resolved object IDs to be stored');

        // Verify all 3 names are present
        $names = $createdTeamObjects->pluck('name')->toArray();
        $this->assertContains('G44.319', $names);
        $this->assertContains('M54.2', $names);
        $this->assertContains('S13.4', $names);
    }

    // =========================================================================
    // buildExtractionResponseSchema() - Schema structure tests
    // =========================================================================

    #[Test]
    public function build_extraction_response_schema_simplifies_to_leaf_level_only(): void
    {
        // Given: A deeply nested schema (provider > care_summary > professional > {name, title})
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'provider' => [
                        'type'       => 'object',
                        'properties' => [
                            'care_summary' => [
                                'type'       => 'object',
                                'properties' => [
                                    'professional' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'name'  => ['type' => 'string'],
                                                'title' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Fragment selector that navigates through the hierarchy
        $identityGroup = [
            'name'              => 'Professional',
            'object_type'       => 'Professional',
            'identity_fields'   => ['name', 'title'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'provider' => [
                        'type'     => 'object',
                        'children' => [
                            'care_summary' => [
                                'type'     => 'object',
                                'children' => [
                                    'professional' => [
                                        'type'     => 'array',
                                        'children' => [
                                            'name'  => ['type' => 'string'],
                                            'title' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building the extraction response schema
        $responseSchema = $this->invokeProtectedMethod(
            $this->service,
            'buildExtractionResponseSchema',
            [$schemaDefinition, $identityGroup, []]
        );

        // Then: Schema should be simplified to leaf level only
        // Expected: { data: { professional: [...] }, ... } - NOT the full hierarchy
        $dataProperties = $responseSchema['properties']['data']['properties'] ?? [];

        // Should have 'professional' at the top level of data, NOT 'provider'
        $this->assertArrayHasKey('professional', $dataProperties,
            'Schema should be simplified to leaf key (professional), not full hierarchy');
        $this->assertArrayNotHasKey('provider', $dataProperties,
            'Schema should NOT include parent keys like provider');
    }

    #[Test]
    public function build_extraction_response_schema_embeds_search_query_in_each_object(): void
    {
        // Given: A schema for array-type extraction (multiple items)
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'professional' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'  => ['type' => 'string'],
                                'title' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $identityGroup = [
            'name'              => 'Professional',
            'object_type'       => 'Professional',
            'identity_fields'   => ['name', 'title'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'professional' => [
                        'type'     => 'array',
                        'children' => [
                            'name'  => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building the extraction response schema
        $responseSchema = $this->invokeProtectedMethod(
            $this->service,
            'buildExtractionResponseSchema',
            [$schemaDefinition, $identityGroup, []]
        );

        // Then: _search_query should be embedded in each object, NOT at top level
        $this->assertArrayNotHasKey('search_query', $responseSchema['properties'],
            '_search_query should NOT be at top level');

        // Get the items schema for the array
        $dataProperties = $responseSchema['properties']['data']['properties'] ?? [];
        $this->assertArrayHasKey('professional', $dataProperties);

        $itemsSchema    = $dataProperties['professional']['items'] ?? [];
        $itemProperties = $itemsSchema['properties']               ?? [];

        $this->assertArrayHasKey('_search_query', $itemProperties,
            'Each object should have embedded _search_query property');

        // Verify _search_query is now an array type with items containing identity fields
        $searchQuerySchema = $itemProperties['_search_query'];
        $this->assertEquals('array', $searchQuerySchema['type'],
            '_search_query should be an array type for progressive query refinement');

        // Verify the items schema has the identity fields
        $searchQueryItemProperties = $searchQuerySchema['items']['properties'] ?? [];
        $this->assertArrayHasKey('name', $searchQueryItemProperties);
        $this->assertArrayHasKey('title', $searchQueryItemProperties);
    }

    #[Test]
    public function build_extraction_response_schema_for_single_object_embeds_search_query(): void
    {
        // Given: A schema for single object extraction (not array)
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'    => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['name'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',  // Single object, not array
                        'children' => [
                            'name'    => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // When: Building the extraction response schema
        $responseSchema = $this->invokeProtectedMethod(
            $this->service,
            'buildExtractionResponseSchema',
            [$schemaDefinition, $identityGroup, []]
        );

        // Then: _search_query should be embedded in the object, NOT at top level
        $this->assertArrayNotHasKey('search_query', $responseSchema['properties'],
            '_search_query should NOT be at top level for single object');

        $dataProperties = $responseSchema['properties']['data']['properties'] ?? [];
        $this->assertArrayHasKey('client', $dataProperties);

        $clientProperties = $dataProperties['client']['properties'] ?? [];
        $this->assertArrayHasKey('_search_query', $clientProperties,
            'Single object should have embedded _search_query property');
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Invoke a protected method on an object for testing.
     */
    private function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function mockAgentThreadBuilder(AgentThread $thread): void
    {
        $this->mock(AgentThreadBuilderService::class, function (MockInterface $mock) use ($thread) {
            $builderMock = Mockery::mock();
            $builderMock->shouldReceive('named')->andReturnSelf();
            $builderMock->shouldReceive('withArtifacts')->andReturnSelf();
            $builderMock->shouldReceive('build')->andReturn($thread);

            $mock->shouldReceive('for')->andReturn($builderMock);
        });
    }

    private function mockAgentThreadService(array $responseData): void
    {
        $mockMessage = $this->createMock(AgentThreadMessage::class);
        $mockMessage->method('getJsonContent')->willReturn($responseData);

        $mockThreadRun              = $this->mock(AgentThreadRun::class)->makePartial();
        $mockThreadRun->lastMessage = $mockMessage;
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

        $this->mock(AgentThreadService::class, function (MockInterface $mock) use ($mockThreadRun) {
            $mock->shouldReceive('withResponseFormat')->andReturnSelf();
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')->andReturn($mockThreadRun);
        });
    }
}
