<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\DataExtraction\ExtractionRollupService;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractionRollupServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractionRollupService $rollupService;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    private Artifact $parentArtifact;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->rollupService = app(ExtractionRollupService::class);

        // Create common test fixtures
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => null,
            'json_content'       => null,
        ]);
        $this->taskRun->outputArtifacts()->attach($this->parentArtifact->id);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create an extraction artifact with the given data.
     *
     * @param  string|null  $relationshipKey  The schema property name (e.g., "providers", "care_summary")
     * @param  bool  $isArrayType  Whether the schema defines this as an array type
     */
    private function createExtractionArtifact(
        array $jsonContent,
        ?int $parentId = null,
        ?string $parentType = null,
        string $operation = 'Extract Identity',
        ?Artifact $parentArtifact = null,
        ?string $relationshipKey = null,
        bool $isArrayType = false
    ): Artifact {
        return Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => $parentArtifact?->id ?? $this->parentArtifact->id,
            'json_content'       => $jsonContent,
            'meta'               => [
                'operation'        => $operation,
                'parent_id'        => $parentId,
                'parent_type'      => $parentType,
                'relationship_key' => $relationshipKey,
                'is_array_type'    => $isArrayType,
            ],
        ]);
    }

    // =========================================================================
    // Basic Rollup Functionality Tests
    // =========================================================================

    #[Test]
    public function rollup_collects_data_from_extraction_artifacts(): void
    {
        // Given: Extraction artifacts with object data
        $this->createExtractionArtifact(
            jsonContent: [
                'id'    => 1,
                'type'  => 'Client',
                'name'  => 'John Smith',
                'email' => 'john@example.com',
                'phone' => '555-1234',
            ],
            parentId: null, // Root object
            parentType: null
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: json_content is populated with rollup data
        $this->parentArtifact->refresh();
        $this->assertNotNull($this->parentArtifact->json_content);
        $this->assertArrayHasKey('extracted_at', $this->parentArtifact->json_content);
        $this->assertArrayHasKey('objects', $this->parentArtifact->json_content);
        $this->assertArrayHasKey('summary', $this->parentArtifact->json_content);

        // Verify Client data is present in objects array
        $objects = $this->parentArtifact->json_content['objects'];
        $this->assertCount(1, $objects);

        // Verify object details
        $clientObject = $objects[0];
        $this->assertEquals(1, $clientObject['id']);
        $this->assertEquals('Client', $clientObject['type']);
        $this->assertEquals('John Smith', $clientObject['name']);
        $this->assertEquals('john@example.com', $clientObject['email']);
        $this->assertEquals('555-1234', $clientObject['phone']);
    }

    #[Test]
    public function rollup_sets_json_content_on_output_artifact(): void
    {
        // Given: Extraction artifact with simple data
        $this->createExtractionArtifact(
            jsonContent: [
                'id'   => 1,
                'type' => 'Provider',
                'name' => 'Dr. Jane Doe',
            ],
            parentId: null,
            parentType: null
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: json_content is set with proper structure
        $this->parentArtifact->refresh();
        $this->assertIsArray($this->parentArtifact->json_content);
        $this->assertArrayHasKey('extracted_at', $this->parentArtifact->json_content);
        $this->assertArrayHasKey('objects', $this->parentArtifact->json_content);
        $this->assertArrayHasKey('summary', $this->parentArtifact->json_content);

        // Verify ISO8601 timestamp format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $this->parentArtifact->json_content['extracted_at']
        );
    }

    #[Test]
    public function rollup_structure_matches_expected_format(): void
    {
        // Given: Multiple root objects
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'name' => 'Test Client'],
            parentId: null,
            parentType: null
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Provider', 'name' => 'Test Provider'],
            parentId: null,
            parentType: null
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Summary counts are accurate
        $this->parentArtifact->refresh();
        $summary = $this->parentArtifact->json_content['summary'];

        $this->assertEquals(2, $summary['total_objects']);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertEquals(1, $summary['by_type']['Client']);
        $this->assertEquals(1, $summary['by_type']['Provider']);

        // Both root objects should be in the objects array
        $objects = $this->parentArtifact->json_content['objects'];
        $this->assertCount(2, $objects);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    #[Test]
    public function rollup_with_no_extraction_artifacts_sets_empty_structure(): void
    {
        // Given: No extraction artifacts (only parent artifact)
        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: json_content is set with empty objects
        $this->parentArtifact->refresh();
        $this->assertIsArray($this->parentArtifact->json_content);
        $this->assertEmpty($this->parentArtifact->json_content['objects']);
        $this->assertEquals(0, $this->parentArtifact->json_content['summary']['total_objects']);
        $this->assertEmpty($this->parentArtifact->json_content['summary']['by_type']);
    }

    #[Test]
    public function rollup_skips_processing_when_json_content_already_exists(): void
    {
        // Given: Parent artifact that already has json_content
        $existingJsonContent = [
            'extracted_at' => '2024-01-01T00:00:00+00:00',
            'objects'      => [['id' => 99, 'type' => 'OldData', 'name' => 'Old']],
            'summary'      => ['total_objects' => 99, 'by_type' => ['OldData' => 99]],
        ];

        $this->parentArtifact->json_content = $existingJsonContent;
        $this->parentArtifact->save();

        // Create a new extraction artifact that would be collected
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'name' => 'New Client'],
            parentId: null,
            parentType: null
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: json_content is NOT overwritten
        $this->parentArtifact->refresh();
        $this->assertEquals($existingJsonContent, $this->parentArtifact->json_content);
    }

    #[Test]
    public function rollup_handles_no_output_artifact_gracefully(): void
    {
        // Given: TaskRun with no output artifacts attached
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // When: Rolling up data (no output artifact attached)
        // Then: Should not throw an exception
        $this->rollupService->rollupTaskRunData($taskRun);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Hierarchical Structure Tests (Using Artifact meta.parent_id)
    // =========================================================================

    #[Test]
    public function rollup_correctly_nests_child_objects_under_parent_via_meta(): void
    {
        // Given: Root object and child object linked via meta.parent_id
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Parent Demand'],
            parentId: null,
            parentType: null
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Client', 'name' => 'Child Client'],
            parentId: 1,
            parentType: 'Demand'
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Child is nested under parent
        $this->parentArtifact->refresh();
        $objects = $this->parentArtifact->json_content['objects'];

        // Only root objects should be in the top-level array
        $this->assertCount(1, $objects);

        $demandInResult = $objects[0];
        $this->assertEquals(1, $demandInResult['id']);
        $this->assertEquals('Demand', $demandInResult['type']);

        // Child should be nested under snake_case of type
        $this->assertArrayHasKey('client', $demandInResult);
        $clientInResult = $demandInResult['client'];
        $this->assertEquals(2, $clientInResult['id']);
        $this->assertEquals('Client', $clientInResult['type']);
    }

    #[Test]
    public function rollup_nests_multiple_children_as_array(): void
    {
        // Given: Parent with multiple children of same type
        // Schema defines "providers" as array type (is_array_type: true)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Demand ABC'],
            parentId: null,
            parentType: null
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Provider', 'name' => 'Provider 1'],
            parentId: 1,
            parentType: 'Demand',
            relationshipKey: 'providers',
            isArrayType: true
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 3, 'type' => 'Provider', 'name' => 'Provider 2'],
            parentId: 1,
            parentType: 'Demand',
            relationshipKey: 'providers',
            isArrayType: true
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Multiple children are nested as array using schema-defined key "providers"
        $this->parentArtifact->refresh();
        $demandInResult = $this->parentArtifact->json_content['objects'][0];

        $this->assertArrayHasKey('providers', $demandInResult);
        $this->assertIsArray($demandInResult['providers']);
        $this->assertCount(2, $demandInResult['providers']);

        $providerIds = array_column($demandInResult['providers'], 'id');
        $this->assertContains(2, $providerIds);
        $this->assertContains(3, $providerIds);
    }

    #[Test]
    public function rollup_derives_relationship_key_from_child_type(): void
    {
        // Given: Relationship using type for key derivation
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Test Demand'],
            parentId: null,
            parentType: null
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Care Summary', 'name' => 'Care Summary Item'],
            parentId: 1,
            parentType: 'Demand'
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Child is nested under snake_case of type
        $this->parentArtifact->refresh();
        $demandInResult = $this->parentArtifact->json_content['objects'][0];

        // 'Care Summary' should become 'care_summary'
        $this->assertArrayHasKey('care_summary', $demandInResult);
        $this->assertEquals(2, $demandInResult['care_summary']['id']);
    }

    #[Test]
    public function rollup_summary_counts_are_accurate(): void
    {
        // Given: Multiple objects of different types
        for ($i = 0; $i < 3; $i++) {
            $this->createExtractionArtifact(
                jsonContent: ['id' => $i + 1, 'type' => 'Client', 'name' => "Client $i"],
                parentId: null,
                parentType: null
            );
        }

        for ($i = 0; $i < 2; $i++) {
            $this->createExtractionArtifact(
                jsonContent: ['id' => $i + 10, 'type' => 'Provider', 'name' => "Provider $i"],
                parentId: null,
                parentType: null
            );
        }

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Summary counts are correct
        $this->parentArtifact->refresh();
        $summary = $this->parentArtifact->json_content['summary'];

        $this->assertEquals(5, $summary['total_objects']);
        $this->assertEquals(3, $summary['by_type']['Client']);
        $this->assertEquals(2, $summary['by_type']['Provider']);
    }

    // =========================================================================
    // buildRollupFromArtifacts Direct Tests
    // =========================================================================

    #[Test]
    public function buildRollupFromArtifacts_handles_empty_collection(): void
    {
        // Given: Empty collection
        $artifacts = new Collection();

        // When: Building rollup structure
        $result = $this->rollupService->buildRollupFromArtifacts($artifacts);

        // Then: Valid structure with zeros
        $this->assertArrayHasKey('extracted_at', $result);
        $this->assertArrayHasKey('objects', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEmpty($result['objects']);
        $this->assertEquals(0, $result['summary']['total_objects']);
        $this->assertEmpty($result['summary']['by_type']);
    }

    #[Test]
    public function buildRollupFromArtifacts_merges_data_from_multiple_artifacts_for_same_object(): void
    {
        // Given: Multiple artifacts for the same object ID (identity + remaining)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'name' => 'John Smith'],
            parentId: null,
            parentType: null,
            operation: ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'email' => 'john@example.com', 'phone' => '555-1234'],
            parentId: null,
            parentType: null,
            operation: ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Data from both artifacts is merged
        $this->parentArtifact->refresh();
        $objects = $this->parentArtifact->json_content['objects'];
        $this->assertCount(1, $objects);

        $clientObject = $objects[0];
        $this->assertEquals('John Smith', $clientObject['name']);
        $this->assertEquals('john@example.com', $clientObject['email']);
        $this->assertEquals('555-1234', $clientObject['phone']);

        // Summary should count as 1 object
        $this->assertEquals(1, $this->parentArtifact->json_content['summary']['total_objects']);
    }

    // =========================================================================
    // getOutputArtifact Tests
    // =========================================================================

    #[Test]
    public function getOutputArtifact_returns_latest_parent_artifact(): void
    {
        // Given: TaskRun with multiple parent artifacts
        $newerArtifact = Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => null,
        ]);
        $this->taskRun->outputArtifacts()->attach($newerArtifact->id);

        // When: Getting output artifact
        $result = $this->rollupService->getOutputArtifact($this->taskRun);

        // Then: Returns the latest (highest ID) parent artifact
        $this->assertNotNull($result);
        $this->assertEquals($newerArtifact->id, $result->id);
    }

    #[Test]
    public function getOutputArtifact_returns_null_when_no_parent_artifact(): void
    {
        // Given: TaskRun with only child artifacts in outputArtifacts
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);

        // Only attach a child artifact to outputArtifacts (not the parent)
        $childArtifact = Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
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
    // Deep Nesting Tests
    // =========================================================================

    #[Test]
    public function rollup_handles_deeply_nested_relationships(): void
    {
        // Given: 3-level hierarchy: Demand -> Provider -> Care Summary
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Test Demand'],
            parentId: null,
            parentType: null
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Provider', 'name' => 'Test Provider'],
            parentId: 1,
            parentType: 'Demand'
        );

        $this->createExtractionArtifact(
            jsonContent: ['id' => 3, 'type' => 'Care Summary', 'name' => 'Test Care Summary'],
            parentId: 2,
            parentType: 'Provider'
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Verify deep nesting
        $this->parentArtifact->refresh();
        $demandInResult = $this->parentArtifact->json_content['objects'][0];

        $this->assertEquals('Demand', $demandInResult['type']);
        $this->assertArrayHasKey('provider', $demandInResult);

        $providerInResult = $demandInResult['provider'];
        $this->assertEquals('Provider', $providerInResult['type']);
        $this->assertArrayHasKey('care_summary', $providerInResult);

        $careSummaryInResult = $providerInResult['care_summary'];
        $this->assertEquals('Care Summary', $careSummaryInResult['type']);
        $this->assertEquals(3, $careSummaryInResult['id']);
    }

    #[Test]
    public function rollup_handles_circular_references_without_infinite_loop(): void
    {
        // Given: Objects that could form a circular reference
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'ObjectA', 'name' => 'Object A'],
            parentId: null,
            parentType: null
        );

        // Child points back to parent (circular)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'ObjectB', 'name' => 'Object B'],
            parentId: 1,
            parentType: 'ObjectA'
        );

        // Create another artifact where ObjectA appears as child of ObjectB
        // This would create a cycle: A -> B -> A
        Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => $this->parentArtifact->id,
            'json_content'       => ['id' => 1, 'type' => 'ObjectA', 'name' => 'Object A Cycle'],
            'meta'               => [
                'operation'   => 'Extract Remaining',
                'parent_id'   => 2,
                'parent_type' => 'ObjectB',
            ],
        ]);

        // When: Rolling up data
        // Then: Should not hang or throw exception
        $this->rollupService->rollupTaskRunData($this->taskRun);

        $this->parentArtifact->refresh();
        $this->assertIsArray($this->parentArtifact->json_content);
        // Just verify it completed without hanging
        $this->assertArrayHasKey('objects', $this->parentArtifact->json_content);
    }

    // =========================================================================
    // Nested Artifact Structure Tests
    // =========================================================================

    #[Test]
    public function rollup_collects_artifacts_from_nested_page_artifacts(): void
    {
        // Given: Page artifacts under parent, with extraction artifacts under pages
        $pageArtifact = Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => $this->parentArtifact->id,
            'json_content'       => null, // Page artifacts don't have extraction data
            'meta'               => ['page_number' => 1],
        ]);

        // Extraction artifact under page artifact
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'name' => 'Extracted Client'],
            parentId: null,
            parentType: null,
            operation: 'Extract Identity',
            parentArtifact: $pageArtifact
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Extraction artifact from nested page is collected
        $this->parentArtifact->refresh();
        $objects = $this->parentArtifact->json_content['objects'];
        $this->assertCount(1, $objects);
        $this->assertEquals('Client', $objects[0]['type']);
        $this->assertEquals('Extracted Client', $objects[0]['name']);
    }

    #[Test]
    public function rollup_extracts_leaf_object_from_hierarchical_json_content(): void
    {
        // Given: Artifact with hierarchical json_content (nested ancestors)
        $this->createExtractionArtifact(
            jsonContent: [
                'id'     => 1,
                'type'   => 'Demand',
                'client' => [
                    'id'    => 2,
                    'type'  => 'Client',
                    'name'  => 'Nested Client',
                    'email' => 'nested@example.com',
                ],
            ],
            parentId: null,
            parentType: null
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: The leaf object (Client) is extracted
        $this->parentArtifact->refresh();
        $objects = $this->parentArtifact->json_content['objects'];

        // Should find the leaf object
        $this->assertCount(1, $objects);
        $this->assertEquals(2, $objects[0]['id']);
        $this->assertEquals('Client', $objects[0]['type']);
        $this->assertEquals('Nested Client', $objects[0]['name']);
        $this->assertEquals('nested@example.com', $objects[0]['email']);
    }

    #[Test]
    public function rollup_filters_only_extraction_operation_artifacts(): void
    {
        // Given: Mix of extraction and non-extraction artifacts
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'name' => 'Extraction Client'],
            parentId: null,
            parentType: null,
            operation: 'Extract Identity'
        );

        // Non-extraction artifact (classification)
        Artifact::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => $this->parentArtifact->id,
            'json_content'       => ['id' => 99, 'type' => 'Classification', 'name' => 'Should Not Appear'],
            'meta'               => [
                'operation' => 'Classify',
            ],
        ]);

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Only extraction artifacts are collected
        $this->parentArtifact->refresh();
        $objects = $this->parentArtifact->json_content['objects'];
        $this->assertCount(1, $objects);
        $this->assertEquals('Client', $objects[0]['type']);
    }

    // =========================================================================
    // Schema-Defined Cardinality Tests
    // =========================================================================

    #[Test]
    public function rollup_nests_single_child_as_object_when_schema_defines_object_type(): void
    {
        // Given: Parent with single child where schema defines object type (is_array_type: false)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Demand ABC'],
            parentId: null,
            parentType: null
        );

        // Child with is_array_type: false - schema says this is a single object relationship
        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Client', 'name' => 'Single Client'],
            parentId: 1,
            parentType: 'Demand',
            relationshipKey: 'client',
            isArrayType: false  // Schema defines this as a single object, NOT an array
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Child is nested as a single object, NOT an array
        $this->parentArtifact->refresh();
        $demandInResult = $this->parentArtifact->json_content['objects'][0];

        $this->assertArrayHasKey('client', $demandInResult);
        // Critical: Should be an object, NOT an array
        $this->assertIsArray($demandInResult['client']);
        $this->assertArrayHasKey('id', $demandInResult['client'], 'Client should be a single object with id, not an array');
        $this->assertEquals(2, $demandInResult['client']['id']);
        $this->assertEquals('Client', $demandInResult['client']['type']);
        $this->assertEquals('Single Client', $demandInResult['client']['name']);
    }

    #[Test]
    public function rollup_nests_single_child_as_array_when_schema_defines_array_type(): void
    {
        // Given: Parent with single child where schema defines array type (is_array_type: true)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Demand XYZ'],
            parentId: null,
            parentType: null
        );

        // Single child but schema says it's an array type
        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Provider', 'name' => 'Solo Provider'],
            parentId: 1,
            parentType: 'Demand',
            relationshipKey: 'providers',
            isArrayType: true  // Schema defines this as an array, even with single child
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Child is nested as an array (even though there's only one)
        $this->parentArtifact->refresh();
        $demandInResult = $this->parentArtifact->json_content['objects'][0];

        $this->assertArrayHasKey('providers', $demandInResult);
        // Critical: Should be an array, NOT a single object
        $this->assertIsArray($demandInResult['providers']);
        $this->assertCount(1, $demandInResult['providers']);
        $this->assertEquals(2, $demandInResult['providers'][0]['id']);
        $this->assertEquals('Solo Provider', $demandInResult['providers'][0]['name']);
    }

    // =========================================================================
    // Object ID Deduplication Tests
    // =========================================================================

    #[Test]
    public function rollup_deduplicates_same_object_id_from_multiple_artifacts(): void
    {
        // Given: Parent object
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Demand', 'name' => 'Test Demand'],
            parentId: null,
            parentType: null
        );

        // Same child object ID appears in multiple artifacts (identity + remaining extraction)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Provider', 'name' => 'Provider Alpha'],
            parentId: 1,
            parentType: 'Demand',
            operation: 'Extract Identity',
            relationshipKey: 'providers',
            isArrayType: true
        );

        // Same object ID (2) from a different artifact (e.g., remaining data extraction)
        $this->createExtractionArtifact(
            jsonContent: ['id' => 2, 'type' => 'Provider', 'specialty' => 'Orthopedics'],
            parentId: 1,
            parentType: 'Demand',
            operation: 'Extract Remaining',
            relationshipKey: 'providers',
            isArrayType: true
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Only one instance of the object appears in the rollup (deduplicated)
        $this->parentArtifact->refresh();
        $demandInResult = $this->parentArtifact->json_content['objects'][0];

        $this->assertArrayHasKey('providers', $demandInResult);
        // Critical: Should have only 1 provider, not 2 (deduplicated by object ID)
        $this->assertCount(1, $demandInResult['providers'], 'Same object ID should appear only once');
        $this->assertEquals(2, $demandInResult['providers'][0]['id']);

        // Summary should count only 2 unique objects (Demand + Provider)
        $summary = $this->parentArtifact->json_content['summary'];
        $this->assertEquals(2, $summary['total_objects']);
    }

    #[Test]
    public function rollup_merges_data_from_duplicate_object_id_artifacts(): void
    {
        // Given: Same object extracted by identity and remaining operations
        // Identity extraction gets name
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'name' => 'John Smith'],
            parentId: null,
            parentType: null,
            operation: 'Extract Identity'
        );

        // Remaining extraction gets additional fields for same object
        $this->createExtractionArtifact(
            jsonContent: ['id' => 1, 'type' => 'Client', 'email' => 'john@example.com', 'phone' => '555-1234'],
            parentId: null,
            parentType: null,
            operation: 'Extract Remaining'
        );

        // When: Rolling up data
        $this->rollupService->rollupTaskRunData($this->taskRun);

        // Then: Data from both artifacts is merged into single object
        $this->parentArtifact->refresh();
        $objects = $this->parentArtifact->json_content['objects'];

        // Should have only 1 object (merged)
        $this->assertCount(1, $objects);
        $clientObject = $objects[0];

        // Should have merged data from both artifacts
        $this->assertEquals(1, $clientObject['id']);
        $this->assertEquals('Client', $clientObject['type']);
        $this->assertEquals('John Smith', $clientObject['name']);
        $this->assertEquals('john@example.com', $clientObject['email']);
        $this->assertEquals('555-1234', $clientObject['phone']);

        // Summary should count as 1 object
        $this->assertEquals(1, $this->parentArtifact->json_content['summary']['total_objects']);
    }
}
