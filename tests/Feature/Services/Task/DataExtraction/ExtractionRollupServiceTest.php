<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Services\Task\DataExtraction\ExtractionRollupService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractionRollupServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractionRollupService $rollupService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->rollupService = app(ExtractionRollupService::class);
    }

    // =========================================================================
    // Basic Rollup Functionality Tests
    // =========================================================================

    #[Test]
    public function rollup_collects_data_from_resolved_objects_in_task_run_meta(): void
    {
        // Given: TaskRun with resolved objects and TeamObjects with attributes
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
            'date'    => Carbon::parse('2024-01-15'),
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'email',
            'text_value'     => 'john@example.com',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'phone',
            'text_value'     => '555-1234',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [
                        0 => [$teamObject->id],
                    ],
                ],
            ],
        ]);

        // Create parent output artifact
        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: json_content is populated with rollup data
        $parentArtifact->refresh();
        $this->assertNotNull($parentArtifact->json_content);
        $this->assertArrayHasKey('extracted_at', $parentArtifact->json_content);
        $this->assertArrayHasKey('object_types', $parentArtifact->json_content);
        $this->assertArrayHasKey('summary', $parentArtifact->json_content);

        // Verify Client data is present
        $this->assertArrayHasKey('Client', $parentArtifact->json_content['object_types']);
        $clientData = $parentArtifact->json_content['object_types']['Client'];
        $this->assertEquals(1, $clientData['count']);
        $this->assertCount(1, $clientData['objects']);

        // Verify object details
        $clientObject = $clientData['objects'][0];
        $this->assertEquals($teamObject->id, $clientObject['id']);
        $this->assertEquals('John Smith', $clientObject['name']);
        $this->assertEquals('2024-01-15', $clientObject['date']);
        $this->assertArrayHasKey('email', $clientObject['attributes']);
        $this->assertEquals('john@example.com', $clientObject['attributes']['email']);
        $this->assertArrayHasKey('phone', $clientObject['attributes']);
        $this->assertEquals('555-1234', $clientObject['attributes']['phone']);
    }

    #[Test]
    public function rollup_sets_json_content_on_output_artifact(): void
    {
        // Given: TaskRun with simple resolved objects
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Dr. Jane Doe',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Provider' => [
                        0 => [$teamObject->id],
                    ],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: json_content is set with proper structure
        $parentArtifact->refresh();
        $this->assertIsArray($parentArtifact->json_content);
        $this->assertArrayHasKey('extracted_at', $parentArtifact->json_content);
        $this->assertArrayHasKey('object_types', $parentArtifact->json_content);
        $this->assertArrayHasKey('summary', $parentArtifact->json_content);

        // Verify ISO8601 timestamp format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $parentArtifact->json_content['extracted_at']
        );
    }

    #[Test]
    public function rollup_structure_matches_expected_format(): void
    {
        // Given: TaskRun with multiple object types
        $client = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $provider = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Test Provider',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client'   => [0 => [$client->id]],
                    'Provider' => [0 => [$provider->id]],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: Summary counts are accurate
        $parentArtifact->refresh();
        $summary = $parentArtifact->json_content['summary'];

        $this->assertEquals(2, $summary['total_objects']);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertEquals(1, $summary['by_type']['Client']);
        $this->assertEquals(1, $summary['by_type']['Provider']);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    #[Test]
    public function rollup_with_empty_resolved_objects_sets_empty_structure(): void
    {
        // Given: TaskRun with no resolved objects
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: json_content is set with empty object_types
        $parentArtifact->refresh();
        $this->assertIsArray($parentArtifact->json_content);
        $this->assertEmpty($parentArtifact->json_content['object_types']);
        $this->assertEquals(0, $parentArtifact->json_content['summary']['total_objects']);
        $this->assertEmpty($parentArtifact->json_content['summary']['by_type']);
    }

    #[Test]
    public function rollup_skips_processing_when_json_content_already_exists(): void
    {
        // Given: TaskRun with parent artifact that already has json_content
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'New Client',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $existingJsonContent = [
            'extracted_at' => '2024-01-01T00:00:00+00:00',
            'object_types' => ['OldData' => ['count' => 99]],
            'summary'      => ['total_objects' => 99, 'by_type' => ['OldData' => 99]],
        ];

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [0 => [$teamObject->id]],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => $existingJsonContent,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: json_content is NOT overwritten
        $parentArtifact->refresh();
        $this->assertEquals($existingJsonContent, $parentArtifact->json_content);
        $this->assertArrayHasKey('OldData', $parentArtifact->json_content['object_types']);
        $this->assertArrayNotHasKey('Client', $parentArtifact->json_content['object_types']);
    }

    #[Test]
    public function rollup_handles_partial_extraction_data(): void
    {
        // Given: TaskRun with some object types having data, others empty
        $client = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client'   => [0 => [$client->id]],
                    'Provider' => [0 => []], // Empty array for this type
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: Only Client data is in the rollup
        $parentArtifact->refresh();
        $this->assertArrayHasKey('Client', $parentArtifact->json_content['object_types']);
        $this->assertArrayNotHasKey('Provider', $parentArtifact->json_content['object_types']);
        $this->assertEquals(1, $parentArtifact->json_content['summary']['total_objects']);
    }

    #[Test]
    public function rollup_handles_no_output_artifact_gracefully(): void
    {
        // Given: TaskRun with no output artifacts attached
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [0 => [1]],
                ],
            ],
        ]);

        // When: Rolling up data (no output artifact attached)
        // Then: Should not throw an exception
        $this->rollupService->rollupTaskRunData($taskRun);

        // No assertion needed - just verify no exception is thrown
        $this->assertTrue(true);
    }

    // =========================================================================
    // Hierarchical Structure Tests
    // =========================================================================

    #[Test]
    public function rollup_correctly_nests_child_objects_under_parent_objects(): void
    {
        // Given: Parent object with child objects
        $parentObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Client',
            'name'           => 'Parent Client',
            'root_object_id' => null,
        ]);

        $childObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Client',
            'name'           => 'Child Client',
            'root_object_id' => $parentObject->id,
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [
                        0 => [$parentObject->id, $childObject->id],
                    ],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: Parent object is at root level, child is nested
        $parentArtifact->refresh();
        $clientObjects = $parentArtifact->json_content['object_types']['Client']['objects'];

        // Only root objects should be at the top level
        $rootObjects = array_filter($clientObjects, fn($obj) => $obj['id'] === $parentObject->id);
        $this->assertCount(1, $rootObjects, 'Only parent object should be at root level');

        // Child should be nested under parent
        $parentInResult = $clientObjects[0];
        $this->assertEquals($parentObject->id, $parentInResult['id']);
        $this->assertArrayHasKey('children', $parentInResult);
        $this->assertArrayHasKey('Client', $parentInResult['children']);
        $this->assertCount(1, $parentInResult['children']['Client']);
        $this->assertEquals($childObject->id, $parentInResult['children']['Client'][0]['id']);
    }

    #[Test]
    public function rollup_nests_cross_type_children_under_root_parent(): void
    {
        // Given: Parent of one type with child of different type
        $clientObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Client',
            'name'           => 'Client ABC',
            'root_object_id' => null,
        ]);

        $insuranceObject = TeamObject::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'type'           => 'Insurance',
            'name'           => 'Insurance Policy XYZ',
            'root_object_id' => $clientObject->id,
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client'    => [0 => [$clientObject->id]],
                    'Insurance' => [0 => [$insuranceObject->id]],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: Insurance is nested under Client
        $parentArtifact->refresh();

        $clientObjects = $parentArtifact->json_content['object_types']['Client']['objects'];
        $this->assertCount(1, $clientObjects);

        $clientInResult = $clientObjects[0];
        $this->assertEquals($clientObject->id, $clientInResult['id']);
        $this->assertArrayHasKey('Insurance', $clientInResult['children']);
        $this->assertCount(1, $clientInResult['children']['Insurance']);
        $this->assertEquals($insuranceObject->id, $clientInResult['children']['Insurance'][0]['id']);
    }

    #[Test]
    public function rollup_summary_counts_are_accurate(): void
    {
        // Given: Multiple objects of different types
        $clients = [];
        for ($i = 0; $i < 3; $i++) {
            $clients[] = TeamObject::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'type'    => 'Client',
                'name'    => "Client $i",
            ]);
        }

        $providers = [];
        for ($i = 0; $i < 2; $i++) {
            $providers[] = TeamObject::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'type'    => 'Provider',
                'name'    => "Provider $i",
            ]);
        }

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client'   => [0 => array_column($clients, 'id')],
                    'Provider' => [0 => array_column($providers, 'id')],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: Summary counts are correct
        $parentArtifact->refresh();
        $summary = $parentArtifact->json_content['summary'];

        $this->assertEquals(5, $summary['total_objects']);
        $this->assertEquals(3, $summary['by_type']['Client']);
        $this->assertEquals(2, $summary['by_type']['Provider']);
    }

    // =========================================================================
    // collectExtractedData and buildRollupStructure Direct Tests
    // =========================================================================

    #[Test]
    public function collectExtractedData_returns_empty_array_when_no_resolved_objects(): void
    {
        // Given: TaskRun with no resolved objects
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [],
        ]);

        // When: Collecting extracted data
        $result = $this->rollupService->collectExtractedData($taskRun);

        // Then: Returns empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function collectExtractedData_loads_attributes_for_team_objects(): void
    {
        // Given: TeamObject with attributes
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'address',
            'text_value'     => '123 Main St',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [0 => [$teamObject->id]],
                ],
            ],
        ]);

        // When: Collecting extracted data
        $result = $this->rollupService->collectExtractedData($taskRun);

        // Then: TeamObject with attributes is returned
        $this->assertArrayHasKey('Client', $result);
        $this->assertCount(1, $result['Client']);

        $collectedObject = $result['Client'][0];
        $this->assertEquals($teamObject->id, $collectedObject->id);
        $this->assertTrue($collectedObject->relationLoaded('attributes'));
        $this->assertCount(1, $collectedObject->attributes);
        $this->assertEquals('address', $collectedObject->attributes->first()->name);
    }

    #[Test]
    public function buildRollupStructure_handles_empty_extracted_data(): void
    {
        // Given: Empty extracted data
        $extractedData = [];

        // When: Building rollup structure
        $result = $this->rollupService->buildRollupStructure($extractedData);

        // Then: Valid structure with zeros
        $this->assertArrayHasKey('extracted_at', $result);
        $this->assertArrayHasKey('object_types', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEmpty($result['object_types']);
        $this->assertEquals(0, $result['summary']['total_objects']);
        $this->assertEmpty($result['summary']['by_type']);
    }

    #[Test]
    public function buildRollupStructure_includes_json_value_attributes(): void
    {
        // Given: TeamObject with json_value attribute
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'metadata',
            'text_value'     => null,
            'json_value'     => ['key1' => 'value1', 'key2' => 'value2'],
        ]);

        // Load attributes for the TeamObject
        $teamObject->load('attributes');

        $extractedData = [
            'Client' => [$teamObject],
        ];

        // When: Building rollup structure
        $result = $this->rollupService->buildRollupStructure($extractedData);

        // Then: JSON value attributes are included
        $clientObject = $result['object_types']['Client']['objects'][0];
        $this->assertArrayHasKey('metadata', $clientObject['attributes']);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $clientObject['attributes']['metadata']);
    }

    // =========================================================================
    // getOutputArtifact Tests
    // =========================================================================

    #[Test]
    public function getOutputArtifact_returns_latest_parent_artifact(): void
    {
        // Given: TaskRun with multiple parent artifacts
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $olderArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);
        $taskRun->outputArtifacts()->attach($olderArtifact->id);

        $newerArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);
        $taskRun->outputArtifacts()->attach($newerArtifact->id);

        // When: Getting output artifact
        $result = $this->rollupService->getOutputArtifact($taskRun);

        // Then: Returns the latest (highest ID) parent artifact
        $this->assertNotNull($result);
        $this->assertEquals($newerArtifact->id, $result->id);
    }

    #[Test]
    public function getOutputArtifact_returns_null_when_no_parent_artifact(): void
    {
        // Given: TaskRun with only child artifacts
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);

        // Only attach a child artifact to outputArtifacts
        $childArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => $parentArtifact->id,
        ]);
        $taskRun->outputArtifacts()->attach($childArtifact->id);

        // When: Getting output artifact
        $result = $this->rollupService->getOutputArtifact($taskRun);

        // Then: Returns null (no parent artifact in outputArtifacts)
        $this->assertNull($result);
    }

    // =========================================================================
    // Multi-Level Resolved Objects Tests
    // =========================================================================

    #[Test]
    public function rollup_handles_objects_from_multiple_levels(): void
    {
        // Given: Objects resolved at different levels
        $level0Object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Level 0 Client',
        ]);

        $level1Object = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Level 1 Client',
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [
                        0 => [$level0Object->id],
                        1 => [$level1Object->id],
                    ],
                ],
            ],
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($taskRun);

        // Then: Both objects from both levels are included
        $parentArtifact->refresh();
        $this->assertEquals(2, $parentArtifact->json_content['summary']['total_objects']);
        $this->assertEquals(2, $parentArtifact->json_content['object_types']['Client']['count']);
    }
}
