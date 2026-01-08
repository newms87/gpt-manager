<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\DataExtraction\ResolvedObjectsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ResolvedObjectsServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ResolvedObjectsService $service;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(ResolvedObjectsService::class);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    // =========================================================================
    // storeInProcessArtifacts() - Basic storage tests
    // =========================================================================

    #[Test]
    public function store_in_process_artifacts_stores_single_object_id(): void
    {
        // Given: TaskProcess with input artifact
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store a single object ID
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 42);

        // Then: The ID should be stored in the artifact
        $artifact->refresh();
        $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Provider', $resolvedObjects);
        $this->assertContains(42, $resolvedObjects['Provider']);
    }

    #[Test]
    public function store_in_process_artifacts_accumulates_multiple_objects_of_same_type(): void
    {
        // Given: TaskProcess with input artifact (empty meta)
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store two different Provider IDs
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 4);
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 7);

        // Then: Both IDs should be stored in the artifact
        $artifact->refresh();
        $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Provider', $resolvedObjects);
        $this->assertCount(2, $resolvedObjects['Provider']);
        $this->assertContains(4, $resolvedObjects['Provider']);
        $this->assertContains(7, $resolvedObjects['Provider']);
    }

    #[Test]
    public function store_in_process_artifacts_stores_multiple_types(): void
    {
        // Given: TaskProcess with input artifact (empty meta)
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store objects of different types
        $this->service->storeInProcessArtifacts($taskProcess, 'Demand', 10);
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 20);
        $this->service->storeInProcessArtifacts($taskProcess, 'Care Summary', 30);

        // Then: All types should be stored with their respective IDs
        $artifact->refresh();
        $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Demand', $resolvedObjects);
        $this->assertArrayHasKey('Provider', $resolvedObjects);
        $this->assertArrayHasKey('Care Summary', $resolvedObjects);
        $this->assertContains(10, $resolvedObjects['Demand']);
        $this->assertContains(20, $resolvedObjects['Provider']);
        $this->assertContains(30, $resolvedObjects['Care Summary']);
    }

    #[Test]
    public function store_in_process_artifacts_does_not_duplicate_same_object_id(): void
    {
        // Given: TaskProcess with input artifact
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store the same type and ID twice
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 5);
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 5);

        // Then: The ID should appear only once (deduplication)
        $artifact->refresh();
        $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Provider', $resolvedObjects);
        $this->assertCount(1, $resolvedObjects['Provider']);
        $this->assertContains(5, $resolvedObjects['Provider']);
    }

    #[Test]
    public function store_in_process_artifacts_updates_all_input_artifacts(): void
    {
        // Given: TaskProcess with MULTIPLE input artifacts
        $artifact1 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);
        $artifact2 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);
        $artifact3 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);
        $taskProcess->load('inputArtifacts');

        // When: Store a resolved object
        $this->service->storeInProcessArtifacts($taskProcess, 'Client', 42);

        // Then: ALL artifacts should have the resolved object
        $artifact1->refresh();
        $artifact2->refresh();
        $artifact3->refresh();

        $this->assertContains(42, $artifact1->meta['resolved_objects']['Client'] ?? []);
        $this->assertContains(42, $artifact2->meta['resolved_objects']['Client'] ?? []);
        $this->assertContains(42, $artifact3->meta['resolved_objects']['Client'] ?? []);
    }

    #[Test]
    public function store_in_process_artifacts_preserves_existing_resolved_objects(): void
    {
        // Given: Artifact with pre-existing resolved_objects
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Provider' => [4],
                    'Demand'   => [100],
                ],
                'other_meta_key' => 'should be preserved',
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store a new Provider ID (same type as existing)
        $this->service->storeInProcessArtifacts($taskProcess, 'Provider', 7);

        // Then: Existing data should be preserved and new ID added
        $artifact->refresh();
        $meta            = $artifact->meta;
        $resolvedObjects = $meta['resolved_objects'] ?? [];

        // Existing Provider ID (4) and new one (7) both present
        $this->assertContains(4, $resolvedObjects['Provider']);
        $this->assertContains(7, $resolvedObjects['Provider']);
        $this->assertCount(2, $resolvedObjects['Provider']);

        // Existing Demand entry preserved
        $this->assertArrayHasKey('Demand', $resolvedObjects);
        $this->assertContains(100, $resolvedObjects['Demand']);

        // Other meta keys preserved
        $this->assertEquals('should be preserved', $meta['other_meta_key']);
    }

    // =========================================================================
    // storeMultipleInProcessArtifacts() - Batch storage tests
    // =========================================================================

    #[Test]
    public function store_multiple_in_process_artifacts_stores_batch_of_ids(): void
    {
        // Given: TaskProcess with input artifact
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store multiple IDs at once
        $this->service->storeMultipleInProcessArtifacts($taskProcess, 'Provider', [1, 2, 3, 4, 5]);

        // Then: All IDs should be stored
        $artifact->refresh();
        $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Provider', $resolvedObjects);
        $this->assertCount(5, $resolvedObjects['Provider']);
        $this->assertEquals([1, 2, 3, 4, 5], $resolvedObjects['Provider']);
    }

    #[Test]
    public function store_multiple_in_process_artifacts_skips_empty_array(): void
    {
        // Given: TaskProcess with input artifact with existing data
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => ['existing_key' => 'existing_value'],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);
        $taskProcess->load('inputArtifacts');

        // When: Store empty array
        $this->service->storeMultipleInProcessArtifacts($taskProcess, 'Provider', []);

        // Then: Artifact should remain unchanged
        $artifact->refresh();
        $this->assertArrayNotHasKey('resolved_objects', $artifact->meta);
        $this->assertEquals('existing_value', $artifact->meta['existing_key']);
    }

    // =========================================================================
    // storeInArtifact() - Direct artifact storage tests
    // =========================================================================

    #[Test]
    public function store_in_artifact_stores_ids_directly(): void
    {
        // Given: Artifact with empty meta
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],
        ]);

        // When: Store IDs directly to artifact
        $this->service->storeInArtifact($artifact, 'Client', [10, 20, 30]);

        // Then: IDs should be stored
        $artifact->refresh();
        $resolvedObjects = $artifact->meta['resolved_objects'] ?? [];

        $this->assertArrayHasKey('Client', $resolvedObjects);
        $this->assertEquals([10, 20, 30], $resolvedObjects['Client']);
    }

    // =========================================================================
    // combineFromArtifacts() - Aggregation tests
    // =========================================================================

    #[Test]
    public function combine_from_artifacts_merges_all_resolved_objects(): void
    {
        // Given: Multiple artifacts with different resolved objects
        $artifact1 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client'   => [1, 2],
                    'Provider' => [10],
                ],
            ],
        ]);
        $artifact2 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client' => [2, 3],  // Overlaps with artifact1
                    'Demand' => [100],
                ],
            ],
        ]);

        // When: Combine resolved objects from both artifacts
        $combined = $this->service->combineFromArtifacts(collect([$artifact1, $artifact2]));

        // Then: All types should be present with deduplicated IDs
        $this->assertArrayHasKey('Client', $combined);
        $this->assertArrayHasKey('Provider', $combined);
        $this->assertArrayHasKey('Demand', $combined);

        // Client IDs should be merged and deduplicated
        $this->assertEqualsCanonicalizing([1, 2, 3], $combined['Client']);
        $this->assertEquals([10], $combined['Provider']);
        $this->assertEquals([100], $combined['Demand']);
    }

    #[Test]
    public function combine_from_artifacts_returns_empty_array_for_no_artifacts(): void
    {
        // When: Combine with empty collection
        $combined = $this->service->combineFromArtifacts(collect());

        // Then: Empty array
        $this->assertEquals([], $combined);
    }

    #[Test]
    public function combine_from_artifacts_handles_artifacts_without_resolved_objects(): void
    {
        // Given: Artifacts with and without resolved objects
        $artifact1 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [],  // No resolved_objects
        ]);
        $artifact2 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client' => [1],
                ],
            ],
        ]);

        // When: Combine
        $combined = $this->service->combineFromArtifacts(collect([$artifact1, $artifact2]));

        // Then: Only includes data from artifact with resolved_objects
        $this->assertArrayHasKey('Client', $combined);
        $this->assertEquals([1], $combined['Client']);
    }

    // =========================================================================
    // getForType() - Type-specific retrieval tests
    // =========================================================================

    #[Test]
    public function get_for_type_returns_ids_for_specific_type(): void
    {
        // Given: Artifacts with multiple types
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client'   => [1, 2, 3],
                    'Provider' => [10, 20],
                    'Demand'   => [100],
                ],
            ],
        ]);

        // When: Get IDs for specific type
        $clientIds = $this->service->getForType(collect([$artifact]), 'Client');

        // Then: Only Client IDs returned
        $this->assertEquals([1, 2, 3], $clientIds);
    }

    #[Test]
    public function get_for_type_returns_empty_array_for_unknown_type(): void
    {
        // Given: Artifact with resolved objects
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client' => [1, 2],
                ],
            ],
        ]);

        // When: Get IDs for type that doesn't exist
        $unknownIds = $this->service->getForType(collect([$artifact]), 'UnknownType');

        // Then: Empty array
        $this->assertEquals([], $unknownIds);
    }

    #[Test]
    public function get_for_type_combines_across_multiple_artifacts(): void
    {
        // Given: Multiple artifacts with same type
        $artifact1 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client' => [1, 2],
                ],
            ],
        ]);
        $artifact2 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'meta'        => [
                'resolved_objects' => [
                    'Client' => [2, 3, 4],
                ],
            ],
        ]);

        // When: Get IDs for Client type
        $clientIds = $this->service->getForType(collect([$artifact1, $artifact2]), 'Client');

        // Then: Combined and deduplicated
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], $clientIds);
    }
}
