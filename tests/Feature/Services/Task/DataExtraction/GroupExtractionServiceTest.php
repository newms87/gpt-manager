<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Services\Task\DataExtraction\GroupExtractionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GroupExtractionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private GroupExtractionService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(GroupExtractionService::class);
    }

    // =========================================================================
    // extractWithSkimMode() tests
    // =========================================================================

    #[Test]
    public function extractWithSkimMode_processes_artifacts_in_batches(): void
    {
        // Given: TaskRun with configured batch size
        $agent            = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [
                'skim_batch_size'      => 2, // Small batch size for testing
                'confidence_threshold' => 4,
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $teamObject  = TeamObject::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create 5 artifacts (should process in 3 batches with batch_size=2)
        $artifacts = collect();
        for ($i = 0; $i < 5; $i++) {
            $artifacts->push(Artifact::factory()->create(['task_run_id' => $taskRun->id]));
        }

        $group = [
            'name'              => 'Test Group',
            'fragment_selector' => ['children' => ['name' => ['type' => 'string']]],
        ];

        // Create partial mock to mock runExtractionOnArtifacts (which makes LLM calls)
        $batchCount  = 0;
        $serviceMock = $this->partialMock(GroupExtractionService::class, function (MockInterface $mock) use (&$batchCount) {
            $mock->shouldReceive('runExtractionOnArtifacts')->andReturnUsing(function () use (&$batchCount) {
                $batchCount++;

                return [
                    'data'       => ['name' => 'Test Name'],
                    'confidence' => ['name' => 2], // Low confidence forces all batches
                ];
            });
        });

        // When: Running skim mode extraction
        $result = $serviceMock->extractWithSkimMode($taskRun, $taskProcess, $group, $artifacts, $teamObject);

        // Then: All batches were processed (low confidence forces all batches)
        $this->assertEquals(3, $batchCount); // 5 artifacts / 2 per batch = 3 batches
        $this->assertIsArray($result);
    }

    #[Test]
    public function extractWithSkimMode_stops_early_when_all_fields_have_high_confidence(): void
    {
        // Given: TaskRun with configured threshold
        $agent            = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [
                'skim_batch_size'      => 2,
                'confidence_threshold' => 4,
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $teamObject  = TeamObject::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create 10 artifacts
        $artifacts = collect();
        for ($i = 0; $i < 10; $i++) {
            $artifacts->push(Artifact::factory()->create(['task_run_id' => $taskRun->id]));
        }

        $group = [
            'name'              => 'Test Group',
            'fragment_selector' => ['children' => ['name' => ['type' => 'string']]],
        ];

        // Create partial mock to mock runExtractionOnArtifacts (which makes LLM calls)
        $batchCount  = 0;
        $serviceMock = $this->partialMock(GroupExtractionService::class, function (MockInterface $mock) use (&$batchCount) {
            $mock->shouldReceive('runExtractionOnArtifacts')->andReturnUsing(function () use (&$batchCount) {
                $batchCount++;

                return [
                    'data'       => ['name' => 'Test Name'],
                    'confidence' => ['name' => 5], // High confidence stops early
                ];
            });
        });

        // When: Running skim mode extraction
        $result = $serviceMock->extractWithSkimMode($taskRun, $taskProcess, $group, $artifacts, $teamObject);

        // Then: Stopped early after first batch (high confidence)
        $this->assertEquals(1, $batchCount);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('name', $result['data']);
    }

    #[Test]
    public function extractWithSkimMode_takes_highest_confidence_score_for_each_field(): void
    {
        // Given: TaskRun setup
        $agent            = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [
                'skim_batch_size'      => 2,
                'confidence_threshold' => 5, // High threshold to force all batches
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $teamObject  = TeamObject::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create 4 artifacts (2 batches)
        $artifacts = collect();
        for ($i = 0; $i < 4; $i++) {
            $artifacts->push(Artifact::factory()->create(['task_run_id' => $taskRun->id]));
        }

        $group = [
            'name'              => 'Test Group',
            'fragment_selector' => ['children' => ['name' => ['type' => 'string']]],
        ];

        // Create partial mock to mock runExtractionOnArtifacts (which makes LLM calls)
        $batchCount  = 0;
        $serviceMock = $this->partialMock(GroupExtractionService::class, function (MockInterface $mock) use (&$batchCount) {
            $mock->shouldReceive('runExtractionOnArtifacts')->andReturnUsing(function () use (&$batchCount) {
                $batchCount++;
                $confidence = $batchCount === 1 ? 2 : 4; // First batch low, second high

                return [
                    'data'       => ['name' => 'Test Name ' . $batchCount],
                    'confidence' => ['name' => $confidence],
                ];
            });
        });

        // When: Running skim mode extraction
        $result = $serviceMock->extractWithSkimMode($taskRun, $taskProcess, $group, $artifacts, $teamObject);

        // Then: Both batches processed (confidence threshold 5 not met)
        $this->assertEquals(2, $batchCount);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // extractExhaustive() tests
    // =========================================================================

    #[Test]
    public function extractExhaustive_processes_all_artifacts(): void
    {
        // Given: TaskRun with artifacts
        $agent            = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $teamObject  = TeamObject::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $artifacts = collect();
        for ($i = 0; $i < 5; $i++) {
            $artifacts->push(Artifact::factory()->create(['task_run_id' => $taskRun->id]));
        }

        $group = [
            'name'              => 'Test Group',
            'fragment_selector' => ['children' => ['name' => ['type' => 'string']]],
        ];

        // Create partial mock to mock runExtractionOnArtifacts (which makes LLM calls)
        $callCount   = 0;
        $serviceMock = $this->partialMock(GroupExtractionService::class, function (MockInterface $mock) use (&$callCount) {
            $mock->shouldReceive('runExtractionOnArtifacts')->once()->andReturnUsing(function () use (&$callCount) {
                $callCount++;

                return [
                    'data' => [
                        'name'  => 'Extracted Name',
                        'email' => 'test@example.com',
                    ],
                    'confidence' => [],
                ];
            });
        });

        // When: Running exhaustive extraction
        $result = $serviceMock->extractExhaustive($taskRun, $taskProcess, $group, $artifacts, $teamObject);

        // Then: Called once with all artifacts (no batching)
        $this->assertEquals(1, $callCount);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('name', $result['data']);
        $this->assertEquals('Extracted Name', $result['data']['name']);
    }

    // =========================================================================
    // allFieldsHaveHighConfidence() tests
    // =========================================================================

    #[Test]
    public function allFieldsHaveHighConfidence_returns_true_when_all_fields_meet_threshold(): void
    {
        // Given: Group with expected fields and matching confidence scores
        $group = [
            'fragment_selector' => [
                'children' => [
                    'name'  => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ],
        ];

        $confidenceScores = [
            'name'  => 4,
            'email' => 5,
        ];

        // When: Checking confidence
        $result = $this->service->allFieldsHaveHighConfidence($group, $confidenceScores, 4);

        // Then: Returns true
        $this->assertTrue($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_field_below_threshold(): void
    {
        // Given: Group with one field below threshold
        $group = [
            'fragment_selector' => [
                'children' => [
                    'name'  => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ],
        ];

        $confidenceScores = [
            'name'  => 5, // Above threshold
            'email' => 2, // Below threshold
        ];

        // When: Checking confidence with threshold 4
        $result = $this->service->allFieldsHaveHighConfidence($group, $confidenceScores, 4);

        // Then: Returns false
        $this->assertFalse($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_field_missing_from_scores(): void
    {
        // Given: Group with field not in scores
        $group = [
            'fragment_selector' => [
                'children' => [
                    'name'  => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ],
        ];

        $confidenceScores = [
            'name' => 5, // Only one field
        ];

        // When: Checking confidence
        $result = $this->service->allFieldsHaveHighConfidence($group, $confidenceScores, 4);

        // Then: Returns false (email is missing)
        $this->assertFalse($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_no_expected_fields(): void
    {
        // Given: Group with empty fragment_selector
        $group = [
            'fragment_selector' => [
                'children' => [],
            ],
        ];

        $confidenceScores = ['name' => 5];

        // When: Checking confidence
        $result = $this->service->allFieldsHaveHighConfidence($group, $confidenceScores, 4);

        // Then: Returns false (no expected fields to match)
        $this->assertFalse($result);
    }

    #[Test]
    public function allFieldsHaveHighConfidence_returns_false_when_no_fragment_selector(): void
    {
        // Given: Group without fragment_selector
        $group = ['name' => 'Test Group'];

        $confidenceScores = ['name' => 5];

        // When: Checking confidence
        $result = $this->service->allFieldsHaveHighConfidence($group, $confidenceScores, 4);

        // Then: Returns false
        $this->assertFalse($result);
    }

    // =========================================================================
    // getExpectedFieldsFromGroup() tests
    // =========================================================================

    #[Test]
    public function getExpectedFieldsFromGroup_returns_field_names_from_fragment_selector(): void
    {
        // Given: Group with fragment selector
        $group = [
            'fragment_selector' => [
                'children' => [
                    'name'          => ['type' => 'string'],
                    'date_of_birth' => ['type' => 'string'],
                    'address'       => ['type' => 'object'],
                ],
            ],
        ];

        // When: Getting expected fields
        $fields = $this->service->getExpectedFieldsFromGroup($group);

        // Then: Returns all field names
        $this->assertCount(3, $fields);
        $this->assertContains('name', $fields);
        $this->assertContains('date_of_birth', $fields);
        $this->assertContains('address', $fields);
    }

    #[Test]
    public function getExpectedFieldsFromGroup_returns_empty_array_when_no_fragment_selector(): void
    {
        // Given: Group without fragment selector
        $group = ['name' => 'Test Group'];

        // When: Getting expected fields
        $fields = $this->service->getExpectedFieldsFromGroup($group);

        // Then: Returns empty array
        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    #[Test]
    public function getExpectedFieldsFromGroup_returns_empty_array_when_no_children(): void
    {
        // Given: Group with fragment selector but no children
        $group = [
            'fragment_selector' => [
                'type' => 'object',
            ],
        ];

        // When: Getting expected fields
        $fields = $this->service->getExpectedFieldsFromGroup($group);

        // Then: Returns empty array
        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    // =========================================================================
    // updateTeamObjectWithExtractedData() tests
    // =========================================================================

    #[Test]
    public function updateTeamObjectWithExtractedData_calls_mapper_with_correct_parameters(): void
    {
        // Given: TaskRun with schema definition
        $schemaDefinition = SchemaDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition   = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
        ]);
        $taskRun = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);

        $teamObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'root_object_id' => null,
        ]);

        $extractedData = ['name' => 'John Smith', 'email' => 'john@example.com'];
        $group         = ['name' => 'Test Group'];

        // Mock the mapper
        $this->mock(JSONSchemaDataToDatabaseMapper::class, function (MockInterface $mock) use ($schemaDefinition, $teamObject, $extractedData) {
            $mock->shouldReceive('setSchemaDefinition')
                ->with(Mockery::on(fn($arg) => $arg->id === $schemaDefinition->id))
                ->once()
                ->andReturnSelf();

            // No root object, so setRootObject should not be called
            $mock->shouldNotReceive('setRootObject');

            $mock->shouldReceive('updateTeamObject')
                ->with(Mockery::on(fn($arg) => $arg->id === $teamObject->id), $extractedData)
                ->once();
        });

        // When: Updating TeamObject
        $this->service->updateTeamObjectWithExtractedData($taskRun, $teamObject, $extractedData, $group);

        // Then: Assertions in mock verify correct calls
    }

    #[Test]
    public function updateTeamObjectWithExtractedData_sets_root_object_when_present(): void
    {
        // Given: TeamObject with root_object_id
        $schemaDefinition = SchemaDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition   = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
        ]);
        $taskRun = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);

        $rootObject = TeamObject::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $teamObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'root_object_id' => $rootObject->id,
        ]);

        $extractedData = ['name' => 'Test'];
        $group         = [];

        // Mock the mapper
        $this->mock(JSONSchemaDataToDatabaseMapper::class, function (MockInterface $mock) use ($rootObject, $teamObject, $extractedData) {
            $mock->shouldReceive('setSchemaDefinition')->andReturnSelf();

            $mock->shouldReceive('setRootObject')
                ->with(Mockery::on(fn($arg) => $arg->id === $rootObject->id))
                ->once()
                ->andReturnSelf();

            $mock->shouldReceive('updateTeamObject')
                ->with(Mockery::on(fn($arg) => $arg->id === $teamObject->id), $extractedData)
                ->once();
        });

        // When: Updating TeamObject
        $this->service->updateTeamObjectWithExtractedData($taskRun, $teamObject, $extractedData, $group);

        // Then: Root object was set
    }

    // =========================================================================
    // buildExtractionPrompt() tests
    // =========================================================================

    #[Test]
    public function buildExtractionPrompt_includes_group_name(): void
    {
        // Given: Group and TeamObject
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $group            = ['name' => 'Client Information'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];

        // When: Building prompt without confidence
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, false);

        // Then: Prompt includes group name
        $this->assertStringContainsString('Client Information', $prompt);
        $this->assertStringContainsString('extracting Client Information data', $prompt);
    }

    #[Test]
    public function buildExtractionPrompt_includes_existing_object_data(): void
    {
        // Given: TeamObject with data
        $teamObject = TeamObject::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'type'        => 'Client',
            'name'        => 'John Smith',
            'description' => 'Important client',
        ]);

        $group            = ['name' => 'Client Info'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];

        // When: Building prompt
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, false);

        // Then: Prompt includes existing object data
        $this->assertStringContainsString('Existing Object Data', $prompt);
        $this->assertStringContainsString('John Smith', $prompt);
        $this->assertStringContainsString('Client', $prompt);
    }

    #[Test]
    public function buildExtractionPrompt_adds_confidence_instructions_when_enabled(): void
    {
        // Given: TeamObject
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $group            = ['name' => 'Test'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];

        // When: Building prompt with confidence enabled
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, true);

        // Then: Prompt includes confidence instructions
        $this->assertStringContainsString('Rate your confidence (1-5)', $prompt);
        $this->assertStringContainsString('confidence', $prompt);
        $this->assertStringContainsString('1 = Very uncertain', $prompt);
        $this->assertStringContainsString('5 = Highly confident', $prompt);
    }

    #[Test]
    public function buildExtractionPrompt_omits_confidence_instructions_when_disabled(): void
    {
        // Given: TeamObject
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $group            = ['name' => 'Test'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];

        // When: Building prompt without confidence
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, false);

        // Then: Prompt does not include confidence instructions
        $this->assertStringNotContainsString('Rate your confidence (1-5)', $prompt);
        $this->assertStringNotContainsString('1 = Very uncertain', $prompt);
    }

    #[Test]
    public function buildExtractionPrompt_includes_extraction_instructions_when_configured(): void
    {
        // Given: TeamObject and config with extraction_instructions
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $group            = ['name' => 'Client Info'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];
        $config           = [
            'extraction_instructions' => 'Focus on extracting billing information from the footer.',
        ];

        // When: Building prompt with extraction_instructions config
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, false, null, $config);

        // Then: Prompt includes extraction instructions
        $this->assertStringContainsString('## Additional Instructions', $prompt);
        $this->assertStringContainsString('Focus on extracting billing information from the footer.', $prompt);
    }

    #[Test]
    public function buildExtractionPrompt_omits_extraction_instructions_when_not_configured(): void
    {
        // Given: TeamObject and config without extraction_instructions
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $group            = ['name' => 'Client Info'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];
        $config           = [
            'some_other_config' => 'value',
            // No extraction_instructions key
        ];

        // When: Building prompt without extraction_instructions
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, false, null, $config);

        // Then: Prompt does NOT include Additional Instructions header
        $this->assertStringNotContainsString('## Additional Instructions', $prompt);
    }

    #[Test]
    public function buildExtractionPrompt_omits_extraction_instructions_when_null_config(): void
    {
        // Given: TeamObject and null config
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $group            = ['name' => 'Client Info'];
        $fragmentSelector = ['children' => ['name' => ['type' => 'string']]];

        // When: Building prompt with null config
        $prompt = $this->service->buildExtractionPrompt($group, $teamObject, $fragmentSelector, false, null, null);

        // Then: Prompt does NOT include Additional Instructions header
        $this->assertStringNotContainsString('## Additional Instructions', $prompt);
    }

    // =========================================================================
    // getExistingObjectData() tests
    // =========================================================================

    #[Test]
    public function getExistingObjectData_returns_basic_fields(): void
    {
        // Given: TeamObject with basic data
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        // When: Getting existing data
        $data = $this->service->getExistingObjectData($teamObject);

        // Then: Contains id, type, and name
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($teamObject->id, $data['id']);
        $this->assertArrayHasKey('type', $data);
        $this->assertEquals('Client', $data['type']);
        $this->assertArrayHasKey('name', $data);
        $this->assertEquals('John Smith', $data['name']);
    }

    #[Test]
    public function getExistingObjectData_includes_date_when_present(): void
    {
        // Given: TeamObject with date
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'date'    => '2024-06-15',
        ]);

        // When: Getting existing data
        $data = $this->service->getExistingObjectData($teamObject);

        // Then: Contains date
        $this->assertArrayHasKey('date', $data);
        $this->assertEquals('2024-06-15', $data['date']);
    }

    #[Test]
    public function getExistingObjectData_includes_description_when_present(): void
    {
        // Given: TeamObject with description
        $teamObject = TeamObject::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'description' => 'This is a test description',
        ]);

        // When: Getting existing data
        $data = $this->service->getExistingObjectData($teamObject);

        // Then: Contains description
        $this->assertArrayHasKey('description', $data);
        $this->assertEquals('This is a test description', $data['description']);
    }

    #[Test]
    public function getExistingObjectData_includes_url_when_present(): void
    {
        // Given: TeamObject with URL
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://example.com/client',
        ]);

        // When: Getting existing data
        $data = $this->service->getExistingObjectData($teamObject);

        // Then: Contains URL
        $this->assertArrayHasKey('url', $data);
        $this->assertEquals('https://example.com/client', $data['url']);
    }

    #[Test]
    public function getExistingObjectData_includes_attributes(): void
    {
        // Given: TeamObject with attributes
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        TeamObjectAttribute::create([
            'team_object_id' => $teamObject->id,
            'name'           => 'custom_field',
            'text_value'     => 'custom value',
        ]);

        TeamObjectAttribute::create([
            'team_object_id' => $teamObject->id,
            'name'           => 'json_field',
            'json_value'     => ['key' => 'value'],
        ]);

        // When: Getting existing data
        $data = $this->service->getExistingObjectData($teamObject);

        // Then: Contains attributes
        $this->assertArrayHasKey('custom_field', $data);
        $this->assertEquals('custom value', $data['custom_field']);
        $this->assertArrayHasKey('json_field', $data);
        $this->assertEquals(['key' => 'value'], $data['json_field']);
    }

    #[Test]
    public function getExistingObjectData_prefers_json_value_over_text_value(): void
    {
        // Given: TeamObject with attribute having both json and text values
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        TeamObjectAttribute::create([
            'team_object_id' => $teamObject->id,
            'name'           => 'mixed_field',
            'text_value'     => 'text value',
            'json_value'     => ['preferred' => 'json value'],
        ]);

        // When: Getting existing data
        $data = $this->service->getExistingObjectData($teamObject);

        // Then: JSON value is preferred
        $this->assertArrayHasKey('mixed_field', $data);
        $this->assertEquals(['preferred' => 'json value'], $data['mixed_field']);
    }

    // =========================================================================
    // runExtractionOnArtifacts() - Schema filtering tests
    // =========================================================================

    #[Test]
    public function runExtractionOnArtifacts_uses_fragment_schema_not_full_schema(): void
    {
        // Given: A full schema with many fields
        $fullSchema = [
            'type'       => 'object',
            'properties' => [
                'care_summary' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'                           => ['type' => 'string'],
                        'surgery'                        => ['type' => 'string'],
                        'causation'                      => ['type' => 'string'],
                        'diagnosis'                      => ['type' => 'string'],
                        'delay_in_care_gap_in_treatment' => ['type' => 'string'],
                        'impairment_rating'              => ['type' => 'string'],
                        'dates_of_service'               => ['type' => 'string'],
                        // Many more fields that should NOT be extracted...
                    ],
                    'required' => ['name', 'surgery', 'causation', 'diagnosis', 'delay_in_care_gap_in_treatment', 'impairment_rating', 'dates_of_service'],
                ],
            ],
        ];

        $agent            = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => $fullSchema,
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => ['extraction_timeout' => 60],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $teamObject  = TeamObject::factory()->create(['team_id' => $this->user->currentTeam->id, 'type' => 'Care Summary']);

        $artifact = Artifact::factory()->create([
            'task_run_id' => $taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        // Fragment selector that only requests 2 fields
        $group = [
            'name'              => 'Care Continuity',
            'object_type'       => 'Care Summary',
            'fields'            => ['delay_in_care_gap_in_treatment', 'impairment_rating'],
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'care_summary' => [
                        'type'     => 'object',
                        'children' => [
                            'delay_in_care_gap_in_treatment' => ['type' => 'string'],
                            'impairment_rating'              => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Create mock AgentThreadRun
        $mockMessage   = $this->mock(AgentThreadMessage::class)->makePartial();
        $mockMessage->shouldReceive('getJsonContent')->andReturn([
            'care_summary' => ['delay_in_care_gap_in_treatment' => 'test', 'impairment_rating' => '5%'],
        ]);

        $mockThreadRun              = $this->mock(AgentThreadRun::class)->makePartial();
        $mockThreadRun->lastMessage = $mockMessage;
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

        // Capture the schema passed to withResponseFormat
        $capturedSchema = null;
        $this->mock(AgentThreadService::class, function (MockInterface $mock) use (&$capturedSchema, $mockThreadRun) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnUsing(function ($schema) use ($mock, &$capturedSchema) {
                    $capturedSchema = $schema;

                    return $mock;
                });
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')->andReturn($mockThreadRun);
        });

        // When: Running extraction
        $this->service->runExtractionOnArtifacts(
            $taskRun,
            $taskProcess,
            $group,
            collect([$artifact]),
            $teamObject,
            false
        );

        // Then: The schema passed should only contain the 2 fields from fragment_selector, NOT the full schema
        $this->assertNotNull($capturedSchema, 'Schema should have been captured');

        // The captured schema should be the filtered fragment schema, not the full SchemaDefinition
        // Currently the bug passes the full SchemaDefinition which has 7+ fields
        // After fix, it should pass a schema with only delay_in_care_gap_in_treatment and impairment_rating
        if ($capturedSchema instanceof SchemaDefinition) {
            $schemaToCheck = $capturedSchema->schema;
        } else {
            $schemaToCheck = $capturedSchema->schema ?? $capturedSchema;
        }

        // The fragment schema should only have the 2 fields specified in fragment_selector
        // New top-level pattern wraps data under 'data.properties', so path is:
        // properties → data → properties → care_summary → properties
        $careSummaryProps = $schemaToCheck['properties']['data']['properties']['care_summary']['properties'] ?? [];
        $this->assertArrayHasKey('delay_in_care_gap_in_treatment', $careSummaryProps, 'Should have delay_in_care_gap_in_treatment field');
        $this->assertArrayHasKey('impairment_rating', $careSummaryProps, 'Should have impairment_rating field');

        // Should NOT have the other fields from full schema
        $this->assertArrayNotHasKey('name', $careSummaryProps, 'Should NOT have name field - fragment_selector only requests 2 fields');
        $this->assertArrayNotHasKey('surgery', $careSummaryProps, 'Should NOT have surgery field - fragment_selector only requests 2 fields');
        $this->assertArrayNotHasKey('diagnosis', $careSummaryProps, 'Should NOT have diagnosis field - fragment_selector only requests 2 fields');
    }
}
