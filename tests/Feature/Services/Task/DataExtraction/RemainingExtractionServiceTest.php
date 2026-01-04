<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\DataExtraction\ExtractionArtifactBuilder;
use App\Services\Task\DataExtraction\GroupExtractionService;
use App\Services\Task\DataExtraction\RemainingExtractionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class RemainingExtractionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private RemainingExtractionService $service;

    private Agent $agent;

    private SchemaDefinition $schemaDefinition;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(RemainingExtractionService::class);

        // Set up common test fixtures
        $this->agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'address' => ['type' => 'string'],
                    'city'    => ['type' => 'string'],
                    'state'   => ['type' => 'string'],
                ],
            ],
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $this->agent->id,
            'schema_definition_id' => $this->schemaDefinition->id,
            'task_runner_config'   => [
                'extraction_timeout'   => 60,
                'confidence_threshold' => 3,
                'skim_batch_size'      => 5,
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
    public function execute_returns_empty_when_team_object_not_found(): void
    {
        // Given: TaskProcess with non-existent TeamObject ID
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $extractionGroup = [
            'name'        => 'Client Address',
            'object_type' => 'Client',
        ];

        // When: Executing with non-existent TeamObject ID
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: 999999,  // Non-existent ID
            searchMode: 'exhaustive'
        );

        // Then: Returns empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function execute_returns_empty_when_no_classified_artifacts(): void
    {
        // Given: TeamObject exists but no classified artifacts match
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $extractionGroup = [
            'name'        => 'Client Address',
            'key'         => 'client_address',
            'object_type' => 'Client',
        ];

        // Mock GroupExtractionService to return empty collection
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->once()
                ->andReturn(collect());
        });

        // When: Executing with no matching artifacts
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // execute() - Extraction mode routing tests
    // =========================================================================

    #[Test]
    public function execute_routes_to_skim_mode_extraction(): void
    {
        // Given: TeamObject and classified artifacts
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => ['classification' => ['client_address' => true]],
        ]);

        $extractionGroup = [
            'name'              => 'Client Address',
            'key'               => 'client_address',
            'object_type'       => 'Client',
            'fragment_selector' => [
                'children' => [
                    'address' => ['type' => 'string'],
                    'city'    => ['type' => 'string'],
                ],
            ],
        ];

        $extractedData = [
            'address' => '123 Main St',
            'city'    => 'Springfield',
        ];

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->once()
                ->andReturn(collect([$classifiedArtifact]));

            // Verify skim mode is called
            $mock->shouldReceive('extractWithSkimMode')
                ->once()
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')
                ->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing with skim mode
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'skim'
        );

        // Then: Returns extracted data
        $this->assertEquals($extractedData, $result);
    }

    #[Test]
    public function execute_routes_to_exhaustive_mode_extraction(): void
    {
        // Given: TeamObject and classified artifacts
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => ['classification' => ['client_address' => true]],
        ]);

        $extractionGroup = [
            'name'              => 'Client Address',
            'key'               => 'client_address',
            'object_type'       => 'Client',
            'fragment_selector' => [
                'children' => [
                    'address' => ['type' => 'string'],
                    'city'    => ['type' => 'string'],
                    'state'   => ['type' => 'string'],
                ],
            ],
        ];

        $extractedData = [
            'address' => '456 Oak Ave',
            'city'    => 'Portland',
            'state'   => 'OR',
        ];

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->once()
                ->andReturn(collect([$classifiedArtifact]));

            // Verify exhaustive mode is called
            $mock->shouldReceive('extractExhaustive')
                ->once()
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')
                ->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing with exhaustive mode (default)
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data
        $this->assertEquals($extractedData, $result);
    }

    #[Test]
    public function execute_defaults_to_exhaustive_mode_for_unknown_search_mode(): void
    {
        // Given: TeamObject and classified artifacts
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $extractionGroup = [
            'name'        => 'Test Group',
            'object_type' => 'Client',
        ];

        $extractedData = ['field' => 'value'];

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->andReturn(collect([$classifiedArtifact]));

            // Verify extractExhaustive is called for unknown mode
            $mock->shouldReceive('extractExhaustive')
                ->once()
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing with unknown search mode
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'unknown_mode'  // Defaults to exhaustive
        );

        // Then: Returns extracted data (using exhaustive mode)
        $this->assertEquals($extractedData, $result);
    }

    // =========================================================================
    // execute() - TeamObject update tests
    // =========================================================================

    #[Test]
    public function execute_updates_team_object_with_extracted_data(): void
    {
        // Given: TeamObject and classified artifacts
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $extractionGroup = [
            'name'        => 'Client Details',
            'object_type' => 'Client',
        ];

        $extractedData = [
            'email' => 'client@example.com',
            'phone' => '555-1234',
        ];

        // Mock GroupExtractionService and verify updateTeamObjectWithExtractedData is called
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData, $teamObject) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->andReturn(collect([$classifiedArtifact]));

            $mock->shouldReceive('extractExhaustive')
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')
                ->with(
                    Mockery::type(TaskRun::class),
                    Mockery::on(fn($obj) => $obj->id === $teamObject->id),
                    $extractedData,
                    Mockery::on(fn($g) => $g['name'] === 'Client Details')
                )
                ->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data
        $this->assertEquals($extractedData, $result);
    }

    // =========================================================================
    // execute() - Artifact building tests
    // =========================================================================

    #[Test]
    public function execute_builds_remaining_artifact(): void
    {
        // Given: TeamObject and classified artifacts
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $extractionGroup = [
            'name'        => 'Contact Info',
            'object_type' => 'Client',
        ];

        $extractedData = ['phone' => '555-0000'];

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->andReturn(collect([$classifiedArtifact]));

            $mock->shouldReceive('extractExhaustive')
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        // Mock and verify ExtractionArtifactBuilder
        $builtArtifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($builtArtifact, $teamObject, $extractedData) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->with(
                    Mockery::type(TaskRun::class),
                    Mockery::type(TaskProcess::class),
                    Mockery::on(fn($obj) => $obj->id === $teamObject->id),
                    Mockery::on(fn($g) => $g['name'] === 'Contact Info'),
                    $extractedData,
                    0,  // level
                    'exhaustive'  // searchMode
                )
                ->once()
                ->andReturn($builtArtifact);
        });

        // When: Executing extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data
        $this->assertEquals($extractedData, $result);
    }

    #[Test]
    public function execute_returns_empty_when_extraction_returns_empty(): void
    {
        // Given: TeamObject and classified artifacts, but extraction returns empty
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $extractionGroup = [
            'name'        => 'Empty Group',
            'object_type' => 'Client',
        ];

        // Mock GroupExtractionService to return empty data
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->andReturn(collect([$classifiedArtifact]));

            $mock->shouldReceive('extractExhaustive')
                ->andReturn([]);  // Empty extraction result

            // updateTeamObjectWithExtractedData should NOT be called
            $mock->shouldNotReceive('updateTeamObjectWithExtractedData');
        });

        // ExtractionArtifactBuilder should NOT be called
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('buildRemainingArtifact');
        });

        // When: Executing extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // execute() - Level handling tests
    // =========================================================================

    #[Test]
    public function execute_passes_correct_level_to_artifact_builder(): void
    {
        // Given: TeamObject at level 2
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Claim',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $extractionGroup = [
            'name'        => 'Claim Details',
            'object_type' => 'Claim',
        ];

        $extractedData = ['claim_amount' => 50000];

        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->andReturn(collect([$classifiedArtifact]));

            $mock->shouldReceive('extractExhaustive')
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        // Verify level is passed correctly to artifact builder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->with(
                    Mockery::any(),
                    Mockery::any(),
                    Mockery::any(),
                    Mockery::any(),
                    Mockery::any(),
                    2,  // Level should be 2
                    Mockery::any()
                )
                ->once()
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing at level 2
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 2,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data
        $this->assertEquals($extractedData, $result);
    }

    #[Test]
    public function execute_uses_group_name_for_logging_when_name_missing(): void
    {
        // Given: Extraction group with only object_type (no name)
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        // Group has object_type but no name
        $extractionGroup = [
            'object_type' => 'Accident',
        ];

        $extractedData = ['location' => 'Highway 101'];

        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($classifiedArtifact, $extractedData) {
            $mock->shouldReceive('getClassifiedArtifactsForGroup')
                ->andReturn(collect([$classifiedArtifact]));

            $mock->shouldReceive('extractExhaustive')
                ->andReturn($extractedData);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildRemainingArtifact')
                ->andReturn(Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]));
        });

        // When: Executing extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data (service handles missing name gracefully)
        $this->assertEquals($extractedData, $result);
    }
}
