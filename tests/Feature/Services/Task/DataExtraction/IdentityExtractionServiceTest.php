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
    public function execute_returns_null_when_no_input_artifacts(): void
    {
        // Given: TaskProcess with no input artifacts
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        // No artifacts attached

        $identityGroup = [
            'name'            => 'Client',
            'object_type'     => 'Client',
            'identity_fields' => ['client_name'],
        ];

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0,
            parentObjectId: null
        );

        // Then: Returns null
        $this->assertNull($result);
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

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        // Mock AgentThreadBuilderService
        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock AgentThreadService to return extraction result
        $this->mockAgentThreadService([
            'data'         => ['client_name' => 'New Client Corp'],
            'search_query' => ['client_name' => '%New%Client%'],
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
            level: 0,
            parentObjectId: null
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

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        // Mock AgentThreadBuilderService
        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);

        // Mock AgentThreadService
        $this->mockAgentThreadService([
            'data'         => ['client_name' => 'Existing Client'],
            'search_query' => ['client_name' => '%Existing%Client%'],
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
                    $existingTeamObject->id,
                    null  // parentObjectId
                )
                ->once()
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0,
            parentObjectId: null
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

        $identityGroup = [
            'name'              => 'Demand',
            'object_type'       => 'Demand',
            'identity_fields'   => ['demand_id'],
            'fragment_selector' => [
                'children' => ['demand_id' => ['type' => 'string']],
            ],
        ];

        // Mock dependencies
        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data'         => ['demand_id' => 'DEM-001'],
            'search_query' => ['demand_id' => '%DEM-001%'],
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
            level: 1,
            parentObjectId: null
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

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data'         => ['client_name' => 'Test Client'],
            'search_query' => ['client_name' => '%Test%'],
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
                    null,  // matchId
                    null   // parentObjectId
                )
                ->once()
                ->andReturn($builtArtifact);
        });

        // When: Executing identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0,
            parentObjectId: null
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

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data'         => ['client_name' => 'John Smith'],
            'search_query' => ['client_name' => '%John%Smith%'],
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
            level: 0,
            parentObjectId: null
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

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
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
            level: 0,
            parentObjectId: null
        );

        // Then: Returns null
        $this->assertNull($result);
    }

    #[Test]
    public function execute_handles_parent_object_id(): void
    {
        // Given: Parent object exists
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Case',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        $thread = AgentThread::factory()->create([
            'agent_id' => $this->agent->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        $this->mockAgentThreadBuilder($thread);
        $this->mockAgentThreadService([
            'data'         => ['client_name' => 'Child Client'],
            'search_query' => ['client_name' => '%Child%'],
        ]);

        // Mock DuplicateRecordResolver to verify parent scope is passed
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) use ($parentObject) {
            $mock->shouldReceive('findCandidates')
                ->with('Client', Mockery::any(), $parentObject->id, Mockery::any())
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

        // When: Executing identity extraction with parent object
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 1,
            parentObjectId: $parentObject->id
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

        $identityGroup = [
            'name'              => 'Demand',
            'object_type'       => 'Demand',
            'identity_fields'   => ['name', 'accident_date'],
            'fragment_selector' => [
                'children' => [
                    'name'          => ['type' => 'string'],
                    'accident_date' => ['type' => 'string'],
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
            'data'         => [
                'name'          => '',
                'accident_date' => '',
            ],
            'search_query' => ['name' => '%%'],
        ]);

        // When: Executing identity extraction with empty data from LLM
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0,
            parentObjectId: null
        );

        // Then: Returns null (no data found, no TeamObject created)
        $this->assertNull($result);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

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
