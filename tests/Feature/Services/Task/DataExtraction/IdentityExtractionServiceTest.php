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
use App\Services\Task\DataExtraction\FindCandidatesResult;
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
            $mock->shouldReceive('findCandidates')->andReturn(new FindCandidatesResult(collect()));
        });

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                [Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]
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

        // Mock DuplicateRecordResolver to return existing object via exact match
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) use ($existingTeamObject) {
            $mock->shouldReceive('findCandidates')->andReturn(
                new FindCandidatesResult(collect([$existingTeamObject]), $existingTeamObject->id)
            );
        });

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) use ($existingTeamObject) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
            $mock->shouldReceive('storeResolvedObjectId')
                ->with(Mockery::any(), 'Client', $existingTeamObject->id, 0)
                ->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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
            $mock->shouldReceive('findCandidates')->andReturn(new FindCandidatesResult(collect()));
        });

        // Mock ExtractionProcessOrchestrator and verify it's called correctly
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
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
                [Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]
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
            $mock->shouldReceive('findCandidates')->andReturn(new FindCandidatesResult(collect()));
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        // Mock and verify ExtractionArtifactBuilder is called
        $builtArtifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($builtArtifact) {
            $mock->shouldReceive('buildIdentityArtifact')
                ->once()
                ->andReturn([$builtArtifact]);
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
            // No exact match found during candidate search - just returns candidates for LLM
            $mock->shouldReceive('findCandidates')->andReturn(
                new FindCandidatesResult(collect([$existingTeamObject]))
            );
            $mock->shouldReceive('resolveDuplicate')->andReturn($resolutionResult);
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                [Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]
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

        // Input artifact for the process
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        // Task process with parent_object_ids in meta (set by ExtractionProcessOrchestrator)
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => [
                'parent_object_ids' => [$parentObject->id],
            ],
        ]);
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
                ->with('Provider', Mockery::any(), $parentObject->id, Mockery::any(), Mockery::any(), Mockery::any())
                ->andReturn(new FindCandidatesResult(collect()));
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                [Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]
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
    public function resolve_object_name_prefers_name_field_over_identity_field_order(): void
    {
        // Given: identity_fields = ["date", "name"] where date comes first
        // But extracted data has both date AND name values
        $identificationData = [
            'date' => '2017-10-23',
            'name' => 'RTD train collision with pedestrians',
        ];
        $identityFields = ['date', 'name'];

        // When: Resolving the object name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'resolveObjectName',
            [$identificationData, $identityFields]
        );

        // Then: Should return the name field, NOT the date
        // The name field should be preferred because it's the canonical "name" field
        $this->assertEquals(
            'RTD train collision with pedestrians',
            $result,
            'resolveObjectName should prefer literal "name" field over identity_fields order'
        );
    }

    #[Test]
    public function resolve_object_name_falls_back_to_identity_fields_when_no_name_field(): void
    {
        // Given: identity_fields with no "name" field, just other identifying fields
        $identificationData = [
            'client_id'   => 'CLT-123',
            'client_code' => 'ABC',
        ];
        $identityFields = ['client_id', 'client_code'];

        // When: Resolving the object name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'resolveObjectName',
            [$identificationData, $identityFields]
        );

        // Then: Should return the first identity field value
        $this->assertEquals(
            'CLT-123',
            $result,
            'resolveObjectName should fall back to first identity field when no name field'
        );
    }

    #[Test]
    public function resolve_object_name_falls_back_to_date_when_name_is_empty(): void
    {
        // Given: identity_fields with date and name, but name is empty
        $identificationData = [
            'date' => '2017-10-23',
            'name' => '',  // Empty string
        ];
        $identityFields = ['date', 'name'];

        // When: Resolving the object name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'resolveObjectName',
            [$identificationData, $identityFields]
        );

        // Then: Should fall back to date, formatted as human-readable name
        $this->assertEquals(
            'October 23rd, 2017',
            $result,
            'resolveObjectName should fall back to date formatted as human-readable name'
        );
    }

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
            $mock->shouldReceive('findCandidates')->andReturn(new FindCandidatesResult(collect()));
        });

        // Track how many times storeResolvedObjectId is called
        $storedObjectIds = [];
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) use (&$storedObjectIds) {
            $mock->shouldReceive('getParentOutputArtifact')->andReturnNull();
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
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

                    return [$artifact];
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
    public function build_extraction_response_schema_has_top_level_search_query_for_array_type(): void
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

        // Then: search_query SHOULD be at top level (new pattern)
        $this->assertArrayHasKey('search_query', $responseSchema['properties'],
            'search_query SHOULD be at top level');

        // Get the items schema for the array
        $dataProperties = $responseSchema['properties']['data']['properties'] ?? [];
        $this->assertArrayHasKey('professional', $dataProperties);

        $itemsSchema    = $dataProperties['professional']['items'] ?? [];
        $itemProperties = $itemsSchema['properties']               ?? [];

        // Objects should NOT have embedded _search_query (old pattern removed)
        $this->assertArrayNotHasKey('_search_query', $itemProperties,
            'Objects should NOT have embedded _search_query (use top-level search_query instead)');

        // Verify the top-level search_query is properly structured
        $searchQuerySchema = $responseSchema['properties']['search_query'];
        $this->assertEquals('array', $searchQuerySchema['type'],
            'search_query should be an array type');
    }

    #[Test]
    public function build_extraction_response_schema_includes_all_fields_for_flat_structure(): void
    {
        // Given: Flat fragment_selector - no nested hierarchy, fields at root level
        // This represents a root-level extraction like "Demand" where all children are scalar types
        $identityGroup = [
            'object_type'       => 'Demand',
            'identity_fields'   => ['name', 'accident_date'],
            'skim_fields'       => ['name', 'accident_date', 'description'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'name'          => ['type' => 'string'],
                    'accident_date' => ['type' => 'string'],
                    'description'   => ['type' => 'string'],
                ],
            ],
        ];

        // Create schema definition with matching structure
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'name'          => ['type' => 'string', 'title' => 'Name'],
                    'accident_date' => ['type' => 'string', 'title' => 'Accident Date'],
                    'description'   => ['type' => 'string', 'title' => 'Description'],
                ],
            ],
        ]);

        // When: Building the extraction response schema
        $result = $this->invokeProtectedMethod(
            app(IdentityExtractionService::class),
            'buildExtractionResponseSchema',
            [$schemaDefinition, $identityGroup, []]
        );

        // Then: For flat structures, the leaf key should be the object type (snake_case)
        $this->assertArrayHasKey('demand', $result['properties']['data']['properties'],
            'For flat structure, leaf key should be object_type as snake_case (demand)');

        $demandSchema = $result['properties']['data']['properties']['demand'];

        // Should have ALL fields, not just 'name' (first child key)
        $this->assertArrayHasKey('properties', $demandSchema,
            'Demand schema should have properties');
        $this->assertArrayHasKey('name', $demandSchema['properties'],
            'Should include name field');
        $this->assertArrayHasKey('accident_date', $demandSchema['properties'],
            'Should include accident_date field');
        $this->assertArrayHasKey('description', $demandSchema['properties'],
            'Should include description field');

        // search_query should be at TOP LEVEL, not embedded in demandSchema
        $this->assertArrayNotHasKey('_search_query', $demandSchema['properties'],
            'Should NOT include _search_query field - it is now at top level');
        $this->assertArrayHasKey('search_query', $result['properties'],
            'search_query should be at top level of response schema');
    }

    #[Test]
    public function build_extraction_response_schema_for_single_object_has_top_level_search_query(): void
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

        // Then: search_query SHOULD be at top level (new pattern)
        $this->assertArrayHasKey('search_query', $responseSchema['properties'],
            'search_query should be at top level for all extractions');

        $dataProperties = $responseSchema['properties']['data']['properties'] ?? [];
        $this->assertArrayHasKey('client', $dataProperties);

        // Client object should NOT have embedded _search_query (old pattern removed)
        $clientProperties = $dataProperties['client']['properties'] ?? [];
        $this->assertArrayNotHasKey('_search_query', $clientProperties,
            'Single object should NOT have embedded _search_query property (use top-level search_query instead)');
    }

    // =========================================================================
    // allFieldsHaveHighConfidence() tests
    // =========================================================================

    #[Test]
    public function allFieldsHaveHighConfidence_returns_true_when_all_fields_confident(): void
    {
        // Given: Identity fields with confidence scores meeting threshold
        $identityFields   = ['name', 'email'];
        $confidenceScores = ['name' => 4, 'email' => 5];
        $threshold        = 4;

        // When: Checking confidence
        $result = $this->invokeProtectedMethod(
            $this->service,
            'allFieldsHaveHighConfidence',
            [$identityFields, $confidenceScores, $threshold]
        );

        // Then: Returns true
        $this->assertTrue($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_field_below_threshold(): void
    {
        // Given: One field below threshold
        $identityFields   = ['name', 'email'];
        $confidenceScores = ['name' => 5, 'email' => 2]; // email below threshold
        $threshold        = 4;

        // When: Checking confidence
        $result = $this->invokeProtectedMethod(
            $this->service,
            'allFieldsHaveHighConfidence',
            [$identityFields, $confidenceScores, $threshold]
        );

        // Then: Returns false
        $this->assertFalse($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_field_missing_from_scores(): void
    {
        // Given: A field is missing from confidence scores
        $identityFields   = ['name', 'email'];
        $confidenceScores = ['name' => 5]; // email missing
        $threshold        = 4;

        // When: Checking confidence
        $result = $this->invokeProtectedMethod(
            $this->service,
            'allFieldsHaveHighConfidence',
            [$identityFields, $confidenceScores, $threshold]
        );

        // Then: Returns false
        $this->assertFalse($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_no_identity_fields(): void
    {
        // Given: Empty identity fields
        $identityFields   = [];
        $confidenceScores = ['name' => 5];
        $threshold        = 4;

        // When: Checking confidence
        $result = $this->invokeProtectedMethod(
            $this->service,
            'allFieldsHaveHighConfidence',
            [$identityFields, $confidenceScores, $threshold]
        );

        // Then: Returns false (continue processing when no fields defined)
        $this->assertFalse($result);
    }

    // =========================================================================
    // resolveOrCreateTeamObject() - Parent-child relationship tests
    // =========================================================================

    #[Test]
    public function resolveOrCreateTeamObject_creates_relationship_when_creating_new_child_with_parent(): void
    {
        // Given: A parent TeamObject exists
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand',
        ]);

        // When: Creating a new child object with a parent
        // relationshipKey is the schema-defined property name (e.g., "providers" from schema)
        $identificationData = ['client_name' => 'New Client Corp'];
        $result             = $this->invokeProtectedMethod(
            $this->service,
            'resolveOrCreateTeamObject',
            [
                $this->taskRun,
                'Provider',  // objectType
                $identificationData,
                'New Client Corp',  // name
                null,  // existingId (null = new object)
                $parentObject->id,  // parentObjectId
                'providers',  // relationshipKey - schema property name
            ]
        );

        // Then: A TeamObject is created
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals('Provider', $result->type);
        $this->assertEquals('New Client Corp', $result->name);

        // And: A TeamObjectRelationship is created using the schema-defined relationship key
        $this->assertDatabaseHas('team_object_relationships', [
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $result->id,
            'relationship_name'      => 'providers',  // Uses schema-defined key, not snake_case of type
        ]);
    }

    #[Test]
    public function resolveOrCreateTeamObject_creates_relationship_when_updating_existing_child_with_parent(): void
    {
        // Given: A parent TeamObject and an existing child TeamObject
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand',
        ]);

        $existingChild = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Existing Provider',
        ]);

        // Note: No relationship exists yet between parent and existing child

        // When: Updating the existing child with a parent reference
        $identificationData = ['client_name' => 'Updated Provider Name'];
        $result             = $this->invokeProtectedMethod(
            $this->service,
            'resolveOrCreateTeamObject',
            [
                $this->taskRun,
                'Provider',  // objectType
                $identificationData,
                'Updated Provider Name',  // name
                $existingChild->id,  // existingId (existing object to update)
                $parentObject->id,  // parentObjectId
                'providers',  // relationshipKey - schema property name
            ]
        );

        // Then: The existing TeamObject is returned (not a new one)
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals($existingChild->id, $result->id);

        // And: A TeamObjectRelationship is created (ensured) linking parent to child
        $this->assertDatabaseHas('team_object_relationships', [
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'providers',
        ]);
    }

    #[Test]
    public function resolveOrCreateTeamObject_does_not_create_relationship_when_no_parent(): void
    {
        // Given: No parent (root object scenario)

        // When: Creating a new object without a parent
        // relationshipKey is still passed but won't be used since parentObjectId is null
        $identificationData = ['name' => 'Root Demand Object'];
        $result             = $this->invokeProtectedMethod(
            $this->service,
            'resolveOrCreateTeamObject',
            [
                $this->taskRun,
                'Demand',  // objectType
                $identificationData,
                'Root Demand Object',  // name
                null,  // existingId (null = new object)
                null,  // parentObjectId (null = no parent, root object)
                'demand',  // relationshipKey - not used when no parent
            ]
        );

        // Then: A TeamObject is created
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals('Demand', $result->type);
        $this->assertEquals('Root Demand Object', $result->name);

        // And: No relationship is created for this root object
        $this->assertDatabaseMissing('team_object_relationships', [
            'related_team_object_id' => $result->id,
        ]);
    }

    #[Test]
    public function resolveOrCreateTeamObject_uses_schema_defined_relationship_key(): void
    {
        // Given: A parent TeamObject exists
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand',
        ]);

        // When: Creating a child object with a schema-defined relationship key
        // The relationshipKey parameter comes directly from the schema (e.g., "care_summary")
        $identificationData = ['name' => 'John Smith'];
        $result             = $this->invokeProtectedMethod(
            $this->service,
            'resolveOrCreateTeamObject',
            [
                $this->taskRun,
                'CareSummary',  // PascalCase objectType
                $identificationData,
                'John Smith',
                null,
                $parentObject->id,
                'care_summary',  // relationshipKey - schema property name
            ]
        );

        // Then: Relationship name uses the schema-defined key (not derived from type)
        $this->assertDatabaseHas('team_object_relationships', [
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $result->id,
            'relationship_name'      => 'care_summary',  // Uses schema-defined key
        ]);
    }

    #[Test]
    public function resolveOrCreateTeamObject_does_not_duplicate_relationship_when_already_exists(): void
    {
        // Given: A parent TeamObject and child with existing relationship
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand',
        ]);

        $existingChild = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Existing Provider',
        ]);

        // Create the relationship first with the schema-defined key "providers"
        \App\Models\TeamObject\TeamObjectRelationship::create([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'providers',
        ]);

        $initialCount = \App\Models\TeamObject\TeamObjectRelationship::where([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'providers',
        ])->count();

        // When: Updating the existing child (relationship already exists)
        $identificationData = ['client_name' => 'Updated Provider'];
        $result             = $this->invokeProtectedMethod(
            $this->service,
            'resolveOrCreateTeamObject',
            [
                $this->taskRun,
                'Provider',
                $identificationData,
                'Updated Provider',
                $existingChild->id,
                $parentObject->id,
                'providers',  // relationshipKey - schema property name
            ]
        );

        // Then: No duplicate relationship created
        $finalCount = \App\Models\TeamObject\TeamObjectRelationship::where([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'providers',
        ])->count();

        $this->assertEquals($initialCount, $finalCount);
        $this->assertEquals(1, $finalCount);
    }

    // =========================================================================
    // formatValueAsName() tests
    // =========================================================================

    #[Test]
    public function formatValueAsName_formats_boolean_true_as_yes(): void
    {
        // Given: A boolean true value
        $value = true;

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns "Yes"
        $this->assertEquals('Yes', $result);
    }

    #[Test]
    public function formatValueAsName_formats_boolean_false_as_no(): void
    {
        // Given: A boolean false value
        $value = false;

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns "No"
        $this->assertEquals('No', $result);
    }

    #[Test]
    public function formatValueAsName_formats_string_true_as_yes(): void
    {
        // Given: A string "true" value
        $value = 'true';

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns "Yes"
        $this->assertEquals('Yes', $result);
    }

    #[Test]
    public function formatValueAsName_formats_string_false_as_no(): void
    {
        // Given: A string "false" value
        $value = 'false';

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns "No"
        $this->assertEquals('No', $result);
    }

    #[Test]
    public function formatValueAsName_formats_date_as_human_readable(): void
    {
        // Given: An ISO date string
        $value = '2017-10-31';

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns human-readable date "October 31st, 2017"
        $this->assertEquals('October 31st, 2017', $result);
    }

    #[Test]
    public function formatValueAsName_formats_integer_with_thousands_separator(): void
    {
        // Given: A large integer
        $value = 1000000;

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns formatted with thousands separator
        $this->assertEquals('1,000,000', $result);
    }

    #[Test]
    public function formatValueAsName_formats_float_with_decimals_and_thousands_separator(): void
    {
        // Given: A large float with decimals
        $value = 1000.33;

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns formatted with thousands separator and 2 decimals
        $this->assertEquals('1,000.33', $result);
    }

    #[Test]
    public function formatValueAsName_preserves_regular_strings(): void
    {
        // Given: A regular string value
        $value = 'John Smith';

        // When: Formatting as name
        $result = $this->invokeProtectedMethod(
            $this->service,
            'formatValueAsName',
            [$value]
        );

        // Then: Returns the string unchanged
        $this->assertEquals('John Smith', $result);
    }

    #[Test]
    public function looksLikeDate_detects_iso_date_format(): void
    {
        // Given: An ISO date string (YYYY-MM-DD)
        $value = '2017-10-31';

        // When: Checking if it looks like a date
        $result = $this->invokeProtectedMethod(
            $this->service,
            'looksLikeDate',
            [$value]
        );

        // Then: Returns true
        $this->assertTrue($result);
    }

    #[Test]
    public function looksLikeDate_detects_us_date_format(): void
    {
        // Given: A US date string (MM/DD/YYYY)
        $value = '10/31/2017';

        // When: Checking if it looks like a date
        $result = $this->invokeProtectedMethod(
            $this->service,
            'looksLikeDate',
            [$value]
        );

        // Then: Returns true
        $this->assertTrue($result);
    }

    #[Test]
    public function looksLikeDate_returns_false_for_regular_string(): void
    {
        // Given: A regular string that is not a date
        $value = 'John Smith';

        // When: Checking if it looks like a date
        $result = $this->invokeProtectedMethod(
            $this->service,
            'looksLikeDate',
            [$value]
        );

        // Then: Returns false
        $this->assertFalse($result);
    }

    #[Test]
    public function looksLikeDate_returns_false_for_numeric_string(): void
    {
        // Given: A numeric string that could be confused with a date
        $value = '12345';

        // When: Checking if it looks like a date
        $result = $this->invokeProtectedMethod(
            $this->service,
            'looksLikeDate',
            [$value]
        );

        // Then: Returns false
        $this->assertFalse($result);
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
