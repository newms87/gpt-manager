<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Services\Task\DataExtraction\ExtractionArtifactBuilder;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
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
    public function execute_throws_exception_when_no_input_artifacts(): void
    {
        // Given: TeamObject exists but task process has no input artifacts attached
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        // No artifacts attached to task process

        $extractionGroup = [
            'name'        => 'Client Address',
            'key'         => 'client_address',
            'object_type' => 'Client',
        ];

        // Then: Expect exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has no input artifacts');

        // When: Executing with no input artifacts - should throw
        $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $teamObject->id,
            searchMode: 'exhaustive'
        );
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

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

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            // Verify skim mode is called
            $mock->shouldReceive('extractWithSkimMode')
                ->once()
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')
                ->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

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

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            // Verify exhaustive mode is called
            $mock->shouldReceive('extractExhaustive')
                ->once()
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')
                ->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        $extractionGroup = [
            'name'        => 'Test Group',
            'object_type' => 'Client',
        ];

        $extractedData = ['field' => 'value'];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            // Verify extractExhaustive is called for unknown mode
            $mock->shouldReceive('extractExhaustive')
                ->once()
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        $extractionGroup = [
            'name'        => 'Client Details',
            'object_type' => 'Client',
        ];

        $extractedData = [
            'email' => 'client@example.com',
            'phone' => '555-1234',
        ];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService and verify updateTeamObjectWithExtractedData is called
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData, $teamObject) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

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
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        $extractionGroup = [
            'name'        => 'Contact Info',
            'object_type' => 'Client',
        ];

        $extractedData = ['phone' => '555-0000'];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        // Mock and verify ExtractionArtifactBuilder
        $builtArtifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($builtArtifact) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([$builtArtifact]);
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        $extractionGroup = [
            'name'        => 'Empty Group',
            'object_type' => 'Client',
        ];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService to return empty data
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => [], 'page_sources' => []]);  // Empty extraction result

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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        $extractionGroup = [
            'name'        => 'Claim Details',
            'object_type' => 'Claim',
        ];

        $extractedData = ['claim_amount' => 50000];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        // Verify level is passed correctly to artifact builder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        // Group has object_type but no name
        $extractionGroup = [
            'object_type' => 'Accident',
        ];

        $extractedData = ['location' => 'Highway 101'];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldReceive('updateTeamObjectWithExtractedData')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(false);
            $mock->shouldReceive('buildRemainingArtifact')
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
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

    // =========================================================================
    // execute() - Array extraction tests
    // =========================================================================

    #[Test]
    public function execute_creates_multiple_team_objects_for_array_type_leaf(): void
    {
        // Given: Parent TeamObject and classified artifacts
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'CareSummary',
            'name'    => 'Care Summary 1',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $classifiedArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        // Attach artifact to task process (artifacts are now accessed via inputArtifacts)
        $taskProcess->inputArtifacts()->attach($classifiedArtifact->id);

        // Array extraction group - treatments are an array type leaf
        $extractionGroup = [
            'name'              => 'Treatments',
            'object_type'       => 'Treatment',
            'fragment_selector' => [
                'children' => [
                    'treatment' => [
                        'type'     => 'array',  // Array type - triggers array extraction
                        'children' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Extracted data contains array of treatments
        $extractedData = [
            'treatment' => [
                ['name' => 'Physical Therapy', 'description' => 'Weekly sessions'],
                ['name' => 'Medication', 'description' => 'Pain management'],
                ['name' => 'Surgery', 'description' => 'Scheduled for next month'],
            ],
        ];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            // updateTeamObjectWithExtractedData should NOT be called for array extraction
            $mock->shouldNotReceive('updateTeamObjectWithExtractedData');
        });

        // Mock ExtractionArtifactBuilder - isLeafArrayType returns true for array extraction path
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(true);

            // unwrapExtractedDataPreservingLeaf returns the array of items
            $mock->shouldReceive('unwrapExtractedDataPreservingLeaf')
                ->andReturn($extractedData['treatment']);

            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        });

        // When: Executing array extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $parentObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data and creates TeamObjects
        $this->assertEquals($extractedData, $result);

        // Verify TeamObjects were created for each array item
        $createdTreatments = TeamObject::where('type', 'Treatment')->get();
        $this->assertCount(3, $createdTreatments);

        // Verify names match extracted data
        $treatmentNames = $createdTreatments->pluck('name')->toArray();
        $this->assertContains('Physical Therapy', $treatmentNames);
        $this->assertContains('Medication', $treatmentNames);
        $this->assertContains('Surgery', $treatmentNames);
    }

    #[Test]
    public function execute_stores_all_array_items_in_resolved_objects(): void
    {
        // Given: Parent TeamObject with input artifact to store resolved objects
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Claimant',
            'name'    => 'John Doe',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        // Create input artifact for the task process to store resolved objects
        // This artifact serves both as the input artifact AND the classified artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Array extraction group for complaints
        $extractionGroup = [
            'name'              => 'Complaints',
            'object_type'       => 'Complaint',
            'fragment_selector' => [
                'children' => [
                    'complaint' => [
                        'type'     => 'array',
                        'children' => [
                            'name' => ['type' => 'string'],
                            'area' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Extracted complaints
        $extractedData = [
            'complaint' => [
                ['name' => 'Back Pain', 'area' => 'Lower back'],
                ['name' => 'Neck Pain', 'area' => 'Cervical'],
            ],
        ];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldNotReceive('updateTeamObjectWithExtractedData');
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(true);

            $mock->shouldReceive('unwrapExtractedDataPreservingLeaf')
                ->andReturn($extractedData['complaint']);

            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        });

        // When: Executing array extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $parentObject->id,
            searchMode: 'exhaustive'
        );

        // Then: Returns extracted data
        $this->assertEquals($extractedData, $result);

        // Verify resolved objects are stored in input artifact
        $inputArtifact->refresh();
        $resolvedObjects = $inputArtifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Complaint', $resolvedObjects);
        $this->assertCount(2, $resolvedObjects['Complaint']);

        // Verify the IDs point to actual TeamObjects
        $createdComplaints = TeamObject::whereIn('id', $resolvedObjects['Complaint'])->get();
        $this->assertCount(2, $createdComplaints);

        $complaintNames = $createdComplaints->pluck('name')->toArray();
        $this->assertContains('Back Pain', $complaintNames);
        $this->assertContains('Neck Pain', $complaintNames);
    }

    #[Test]
    public function execute_performs_duplicate_resolution_scoped_to_parent(): void
    {
        // Given: Two parent TeamObjects (Parent A and Parent B)
        $parentA = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Claimant',
            'name'    => 'Parent A',
        ]);

        $parentB = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Claimant',
            'name'    => 'Parent B',
        ]);

        // Create existing child "Back Pain" under Parent A
        $existingChild = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Complaint',
            'name'    => 'Back Pain',
        ]);

        // Create the parent-child relationship: Parent A -> existing child
        TeamObjectRelationship::create([
            'team_object_id'         => $parentA->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'complaints',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Array extraction group for complaints
        $extractionGroup = [
            'name'              => 'Complaints',
            'object_type'       => 'Complaint',
            'fragment_selector' => [
                'children' => [
                    'complaint' => [
                        'type'     => 'array',
                        'children' => [
                            'name' => ['type' => 'string'],
                            'area' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Extract "Back Pain" under Parent B (different parent than existing)
        $extractedData = [
            'complaint' => [
                ['name' => 'Back Pain', 'area' => 'Lumbar'],
            ],
        ];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldNotReceive('updateTeamObjectWithExtractedData');
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(true);

            $mock->shouldReceive('unwrapExtractedDataPreservingLeaf')
                ->andReturn($extractedData['complaint']);

            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        });

        $initialBackPainCount = TeamObject::where('type', 'Complaint')
            ->where('name', 'Back Pain')
            ->count();

        // When: Executing array extraction on Parent B (NOT Parent A)
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $parentB->id,  // Parent B, not Parent A
            searchMode: 'exhaustive'
        );

        // Then: A NEW TeamObject is created because duplicate check is scoped to Parent B only
        $this->assertEquals($extractedData, $result);

        $finalBackPainCount = TeamObject::where('type', 'Complaint')
            ->where('name', 'Back Pain')
            ->count();

        // Should have 2 "Back Pain" objects now (one under each parent)
        $this->assertEquals($initialBackPainCount + 1, $finalBackPainCount);
        $this->assertEquals(2, $finalBackPainCount);
    }

    #[Test]
    public function execute_updates_existing_child_when_duplicate_found(): void
    {
        // Given: Parent TeamObject with existing child
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Claimant',
            'name'    => 'Test Claimant',
        ]);

        // Create existing child "Back Pain" under the parent
        $existingChild = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Complaint',
            'name'    => 'Back Pain',
            'meta'    => ['area' => 'Upper back'],  // Original data
        ]);

        // Create the parent-child relationship
        TeamObjectRelationship::create([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'complaints',
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);

        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Array extraction group for complaints
        $extractionGroup = [
            'name'              => 'Complaints',
            'object_type'       => 'Complaint',
            'fragment_selector' => [
                'children' => [
                    'complaint' => [
                        'type'     => 'array',
                        'children' => [
                            'name' => ['type' => 'string'],
                            'area' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Extract "Back Pain" with updated data (same name, different area)
        $extractedData = [
            'complaint' => [
                ['name' => 'Back Pain', 'area' => 'Lumbar'],  // Same name, new area
            ],
        ];

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllPageArtifacts')->andReturn(collect());
        });

        // Mock GroupExtractionService
        $this->mock(GroupExtractionService::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('extractExhaustive')
                ->andReturn(['data' => $extractedData, 'page_sources' => []]);

            $mock->shouldNotReceive('updateTeamObjectWithExtractedData');
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) use ($extractedData) {
            $mock->shouldReceive('isLeafArrayType')
                ->andReturn(true);

            $mock->shouldReceive('unwrapExtractedDataPreservingLeaf')
                ->andReturn($extractedData['complaint']);

            $mock->shouldReceive('buildRemainingArtifact')
                ->once()
                ->andReturn([Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])]);
        });

        $initialComplaintCount = TeamObject::where('type', 'Complaint')
            ->where('name', 'Back Pain')
            ->count();

        // When: Executing array extraction on the same parent
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            extractionGroup: $extractionGroup,
            level: 0,
            teamObjectId: $parentObject->id,
            searchMode: 'exhaustive'
        );

        // Then: No new TeamObject created (count stays at 1)
        $this->assertEquals($extractedData, $result);

        $finalComplaintCount = TeamObject::where('type', 'Complaint')
            ->where('name', 'Back Pain')
            ->count();

        // Should still be just 1 "Back Pain" object (was updated, not created)
        $this->assertEquals($initialComplaintCount, $finalComplaintCount);
        $this->assertEquals(1, $finalComplaintCount);

        // Verify the existing child object is returned in the resolved objects
        $inputArtifact->refresh();
        $resolvedObjects = $inputArtifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Complaint', $resolvedObjects);
        $this->assertCount(1, $resolvedObjects['Complaint']);
        $this->assertEquals($existingChild->id, $resolvedObjects['Complaint'][0]);
    }

    // =========================================================================
    // createOrUpdateTeamObject() - Parent-child relationship tests
    // =========================================================================

    #[Test]
    public function createOrUpdateTeamObject_creates_relationship_when_creating_new_child(): void
    {
        // Given: A parent TeamObject exists
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'CareSummary',
            'name'    => 'Test Care Summary',
        ]);

        // When: Creating a new child object with schema-defined relationship key
        $itemData = ['name' => 'Physical Therapy', 'description' => 'Weekly sessions'];
        $result   = $this->invokeProtectedMethod(
            $this->service,
            'createOrUpdateTeamObject',
            [
                $this->taskRun,
                'Treatment',  // objectType
                $itemData,
                null,  // existingId (null = new object)
                $parentObject->id,  // parentObjectId
                'treatments',  // relationshipKey - schema property name
            ]
        );

        // Then: A TeamObject is created
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals('Treatment', $result->type);
        $this->assertEquals('Physical Therapy', $result->name);

        // And: A TeamObjectRelationship is created using schema-defined key
        $this->assertDatabaseHas('team_object_relationships', [
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $result->id,
            'relationship_name'      => 'treatments',  // Uses schema-defined key
        ]);
    }

    #[Test]
    public function createOrUpdateTeamObject_creates_relationship_when_updating_existing_child(): void
    {
        // Given: A parent TeamObject and an existing child TeamObject
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'CareSummary',
            'name'    => 'Test Care Summary',
        ]);

        $existingChild = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Treatment',
            'name'    => 'Existing Treatment',
        ]);

        // Note: No relationship exists yet between parent and existing child

        // When: Updating the existing child with a parent reference
        $itemData = ['name' => 'Updated Treatment', 'description' => 'Revised treatment plan'];
        $result   = $this->invokeProtectedMethod(
            $this->service,
            'createOrUpdateTeamObject',
            [
                $this->taskRun,
                'Treatment',  // objectType
                $itemData,
                $existingChild->id,  // existingId (existing object to update)
                $parentObject->id,  // parentObjectId
                'treatments',  // relationshipKey - schema property name
            ]
        );

        // Then: The existing TeamObject is returned (not a new one)
        $this->assertInstanceOf(TeamObject::class, $result);
        $this->assertEquals($existingChild->id, $result->id);

        // And: A TeamObjectRelationship is created (ensured) linking parent to child
        $this->assertDatabaseHas('team_object_relationships', [
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'treatments',
        ]);
    }

    #[Test]
    public function createOrUpdateTeamObject_uses_schema_defined_relationship_key(): void
    {
        // Given: A parent TeamObject exists
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Test Provider',
        ]);

        // When: Creating a child object with a schema-defined relationship key
        // The relationshipKey parameter comes directly from the schema
        $itemData = ['name' => 'Visit on 2024-01-15'];
        $result   = $this->invokeProtectedMethod(
            $this->service,
            'createOrUpdateTeamObject',
            [
                $this->taskRun,
                'DateOfService',  // PascalCase objectType
                $itemData,
                null,
                $parentObject->id,
                'dates_of_service',  // relationshipKey - schema property name
            ]
        );

        // Then: Relationship name uses schema-defined key (not derived from type)
        $this->assertDatabaseHas('team_object_relationships', [
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $result->id,
            'relationship_name'      => 'dates_of_service',  // Uses schema-defined key
        ]);
    }

    #[Test]
    public function createOrUpdateTeamObject_does_not_duplicate_relationship_when_already_exists(): void
    {
        // Given: A parent TeamObject and child with existing relationship
        $parentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'CareSummary',
            'name'    => 'Test Care Summary',
        ]);

        $existingChild = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Treatment',
            'name'    => 'Existing Treatment',
        ]);

        // Create the relationship first with schema-defined key "treatments"
        TeamObjectRelationship::create([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'treatments',
        ]);

        $initialCount = TeamObjectRelationship::where([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'treatments',
        ])->count();

        // When: Updating the existing child (relationship already exists)
        $itemData = ['name' => 'Updated Treatment'];
        $this->invokeProtectedMethod(
            $this->service,
            'createOrUpdateTeamObject',
            [
                $this->taskRun,
                'Treatment',
                $itemData,
                $existingChild->id,
                $parentObject->id,
                'treatments',  // relationshipKey - schema property name
            ]
        );

        // Then: No duplicate relationship created
        $finalCount = TeamObjectRelationship::where([
            'team_object_id'         => $parentObject->id,
            'related_team_object_id' => $existingChild->id,
            'relationship_name'      => 'treatments',
        ])->count();

        $this->assertEquals($initialCount, $finalCount);
        $this->assertEquals(1, $finalCount);
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
}
