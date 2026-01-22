<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractionProcessOrchestratorTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractionProcessOrchestrator $orchestrator;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->orchestrator = app(ExtractionProcessOrchestrator::class);
    }

    #[Test]
    public function getLevelProgress_returns_empty_array_for_new_task_run(): void
    {
        // Given: New TaskRun with no progress
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // When: Getting level progress
        $progress = $this->orchestrator->getLevelProgress($taskRun);

        // Then: Returns empty array
        $this->assertIsArray($progress);
        $this->assertEmpty($progress);
    }

    #[Test]
    public function updateLevelProgress_stores_progress_in_task_run_meta(): void
    {
        // Given: TaskRun
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // When: Updating level progress
        $this->orchestrator->updateLevelProgress($taskRun, 0, 'classification_complete', true);

        // Then: Progress is stored in meta
        $taskRun->refresh();
        $this->assertArrayHasKey('level_progress', $taskRun->meta);
        $this->assertArrayHasKey(0, $taskRun->meta['level_progress']);
        $this->assertTrue($taskRun->meta['level_progress'][0]['classification_complete']);
    }

    #[Test]
    public function isLevelComplete_returns_false_when_not_all_phases_complete(): void
    {
        // Given: TaskRun with partial progress
        // Note: isLevelComplete() only checks identity_complete and extraction_complete
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'level_progress' => [
                    0 => [
                        'identity_complete'   => true,
                        'extraction_complete' => false, // Not complete!
                    ],
                ],
            ],
        ]);

        // When: Checking if level is complete
        $isComplete = $this->orchestrator->isLevelComplete($taskRun, 0);

        // Then: Returns false
        $this->assertFalse($isComplete);
    }

    #[Test]
    public function isLevelComplete_returns_true_when_all_phases_complete(): void
    {
        // Given: TaskRun with all phases complete
        // Note: isLevelComplete() only checks identity_complete and extraction_complete
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'level_progress' => [
                    0 => [
                        'identity_complete'   => true,
                        'extraction_complete' => true,
                    ],
                ],
            ],
        ]);

        // When: Checking if level is complete
        $isComplete = $this->orchestrator->isLevelComplete($taskRun, 0);

        // Then: Returns true
        $this->assertTrue($isComplete);
    }

    #[Test]
    public function getCurrentLevel_returns_zero_for_new_task_run(): void
    {
        // Given: New TaskRun
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // When: Getting current level
        $currentLevel = $this->orchestrator->getCurrentLevel($taskRun);

        // Then: Returns 0
        $this->assertEquals(0, $currentLevel);
    }

    #[Test]
    public function advanceToNextLevel_increments_level_when_current_complete(): void
    {
        // Given: TaskRun with level 0 complete and plan in TaskDefinition.meta
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta'    => [
                'extraction_plan' => [
                    'levels' => [
                        ['level' => 0],
                        ['level' => 1],
                    ],
                ],
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'current_level'  => 0,
                'level_progress' => [
                    0 => [
                        'identity_complete'   => true,
                        'extraction_complete' => true,
                    ],
                ],
            ],
        ]);

        // When: Advancing to next level
        $advanced = $this->orchestrator->advanceToNextLevel($taskRun);

        // Then: Level is incremented
        $this->assertTrue($advanced);
        $taskRun->refresh();
        $this->assertEquals(1, $this->orchestrator->getCurrentLevel($taskRun));
    }

    #[Test]
    public function advanceToNextLevel_returns_false_when_at_max_level(): void
    {
        // Given: TaskRun already at max level with plan in TaskDefinition.meta
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta'    => [
                'extraction_plan' => [
                    'levels' => [
                        ['level' => 0],
                        ['level' => 1],
                    ],
                ],
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'current_level'  => 1,
                'level_progress' => [
                    1 => [
                        'identity_complete'   => true,
                        'extraction_complete' => true,
                    ],
                ],
            ],
        ]);

        // When: Attempting to advance
        $advanced = $this->orchestrator->advanceToNextLevel($taskRun);

        // Then: Returns false, level unchanged
        $this->assertFalse($advanced);
        $taskRun->refresh();
        $this->assertEquals(1, $this->orchestrator->getCurrentLevel($taskRun));
    }

    #[Test]
    public function createExtractIdentityProcesses_creates_process_for_each_identity_group(): void
    {
        // Given: TaskRun with plan containing identity groups
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // Create parent output artifact
        $parentArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);

        // Create child artifact with classification for "Client Identification"
        $childArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => [
                'classification' => [
                    'client_identification' => true,
                ],
            ],
        ]);

        // Attach as output artifacts to task run
        $taskRun->outputArtifacts()->attach($parentArtifact->id);
        $taskRun->outputArtifacts()->attach($childArtifact->id);

        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'              => 'Client Identification',
                            'object_type'       => 'Client',
                            'identity_fields'   => ['name', 'date_of_birth'],
                            'fragment_selector' => [
                                'children' => [
                                    'name'          => ['type' => 'string'],
                                    'date_of_birth' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        // Refresh task run to load relationships
        $taskRun->refresh();

        // When: Creating Extract Identity processes
        $processes = $this->orchestrator->createExtractIdentityProcesses($taskRun, $plan, 0);

        // Then: Process is created for each identity group
        $this->assertCount(1, $processes);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY, $processes[0]->operation);
        $this->assertEquals(0, $processes[0]->meta['level']);

        // Verify input artifacts are attached (includes config artifact + classified artifacts)
        $inputArtifacts = $processes[0]->inputArtifacts()->get();
        $this->assertGreaterThan(0, $inputArtifacts->count());

        // Verify config artifact contains identity_group (architecture moved config to artifact)
        $configArtifact = $inputArtifacts->firstWhere('name', 'Process Config');
        $this->assertNotNull($configArtifact, 'Process Config artifact should be attached');
        $this->assertArrayHasKey('identity_group', $configArtifact->meta);
    }

    #[Test]
    public function isIdentityCompleteForLevel_returns_true_when_all_identity_processes_complete(): void
    {
        // Given: TaskRun with completed identity processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        $process = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'         => ['level' => 0],
            'completed_at' => now(),
        ]);

        // When: Checking if identity is complete
        $isComplete = $this->orchestrator->isIdentityCompleteForLevel($taskRun, 0);

        // Then: Returns true
        $this->assertTrue($isComplete);
    }

    #[Test]
    public function createExtractRemainingProcesses_creates_process_for_each_resolved_object(): void
    {
        // Given: TaskRun with resolved objects
        $client1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $client2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Jane Doe',
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [
                        0 => [$client1->id, $client2->id],
                    ],
                ],
            ],
        ]);

        // Create parent artifact attached to task run's output artifacts
        $parentArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'team_id'            => $this->user->currentTeam->id,
            'parent_artifact_id' => null,
        ]);
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // Create classified child artifact(s) - group key = Str::snake('Client Details') = 'client_details'
        Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'team_id'            => $this->user->currentTeam->id,
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => [
                'classification' => [
                    'client_details' => true,
                ],
            ],
        ]);

        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [
                        [
                            'name'              => 'Client Details',
                            'object_type'       => 'Client',
                            'fragment_selector' => [
                                'children' => [
                                    'address' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When: Creating extract remaining processes
        $processes = $this->orchestrator->createExtractRemainingProcesses($taskRun, $plan, 0);

        // Then: Process created for each resolved object
        $this->assertCount(2, $processes);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING, $processes[0]->operation);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING, $processes[1]->operation);

        $objectIds = array_column(array_map(fn($p) => $p->meta, $processes), 'object_id');
        $this->assertContains($client1->id, $objectIds);
        $this->assertContains($client2->id, $objectIds);
    }

    #[Test]
    public function createExtractRemainingProcesses_skips_identification_groups(): void
    {
        // Given: TaskRun with resolved objects
        $client = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [
                        0 => [$client->id],
                    ],
                ],
            ],
        ]);

        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'              => 'Client ID',
                            'object_type'       => 'Client',
                            'identity_fields'   => ['name'],
                            'fragment_selector' => [
                                'children' => [
                                    'name' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        // When: Creating extract remaining processes
        $processes = $this->orchestrator->createExtractRemainingProcesses($taskRun, $plan, 0);

        // Then: No processes created (identification groups are skipped)
        $this->assertCount(0, $processes);
    }

    #[Test]
    public function storeResolvedObjectId_adds_object_id_to_task_run_meta(): void
    {
        // Given: TaskRun
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // When: Storing resolved object ID
        $this->orchestrator->storeResolvedObjectId($taskRun, 'Client', 123, 0);

        // Then: Object ID is stored in meta
        $taskRun->refresh();
        $this->assertArrayHasKey('resolved_objects', $taskRun->meta);
        $this->assertArrayHasKey('Client', $taskRun->meta['resolved_objects']);
        $this->assertArrayHasKey(0, $taskRun->meta['resolved_objects']['Client']);
        $this->assertContains(123, $taskRun->meta['resolved_objects']['Client'][0]);
    }

    #[Test]
    public function storeResolvedObjectId_prevents_duplicate_object_ids(): void
    {
        // Given: TaskRun
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // When: Storing same object ID twice
        $this->orchestrator->storeResolvedObjectId($taskRun, 'Client', 123, 0);
        $this->orchestrator->storeResolvedObjectId($taskRun, 'Client', 123, 0);

        // Then: Object ID appears only once
        $taskRun->refresh();
        $clientIds = $taskRun->meta['resolved_objects']['Client'][0];
        $this->assertCount(1, $clientIds);
        $this->assertEquals(123, $clientIds[0]);
    }

    #[Test]
    public function getParentObjectIds_returns_empty_for_level_zero(): void
    {
        // Given: TaskRun at level 0
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        // When: Getting parent object IDs for level 0
        $parentIds = $this->orchestrator->getParentObjectIds($taskRun, 0);

        // Then: Returns empty array
        $this->assertIsArray($parentIds);
        $this->assertEmpty($parentIds);
    }

    #[Test]
    public function getParentObjectIds_returns_objects_from_previous_level(): void
    {
        // Given: TaskRun with objects at level 0
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client'  => [
                        0 => [1, 2],
                    ],
                    'Accident' => [
                        0 => [3],
                    ],
                ],
            ],
        ]);

        // When: Getting parent object IDs for level 1
        $parentIds = $this->orchestrator->getParentObjectIds($taskRun, 1);

        // Then: Returns all object IDs from level 0
        $this->assertCount(3, $parentIds);
        $this->assertContains(1, $parentIds);
        $this->assertContains(2, $parentIds);
        $this->assertContains(3, $parentIds);
    }

    #[Test]
    public function create_extract_identity_processes_uses_fresh_classification_data(): void
    {
        // This test verifies that createExtractIdentityProcesses() correctly finds
        // classified artifacts. The method must use ->children()->get() instead of
        // ->children property to avoid stale cached relationship data.
        //
        // Bug scenario: If a parent artifact's children relationship is loaded before
        // classification data is written, the cached ->children property would return
        // stale data without classification meta.

        // Given: TaskRun with TaskDefinition
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [],
        ]);

        // Create parent output artifact
        $parentArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => null,
        ]);

        // Create child page artifact WITH classification meta
        $pageArtifact = Artifact::factory()->create([
            'task_run_id'        => $taskRun->id,
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => ['classification' => ['demand_identification' => true]],
        ]);

        // Attach parent artifact to task run as output artifact
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // Extraction plan with identity group for "Demand"
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'              => 'Demand Identification',
                            'object_type'       => 'Demand',
                            'identity_fields'   => ['name', 'amount'],
                            'fragment_selector' => [
                                'children' => [
                                    'name'   => ['type' => 'string'],
                                    'amount' => ['type' => 'number'],
                                ],
                            ],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];

        // When: Creating Extract Identity processes
        $processes = $this->orchestrator->createExtractIdentityProcesses($taskRun, $plan, 0);

        // Then: Process should be created for the classified page
        $this->assertCount(1, $processes, 'Expected 1 Extract Identity process');
        $this->assertEquals(
            ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            $processes[0]->operation
        );

        // Verify input artifacts include both config artifact and page artifact
        $inputArtifacts = $processes[0]->inputArtifacts()->get();
        $this->assertCount(2, $inputArtifacts, 'Expected config artifact and page artifact to be attached as input');

        // Verify config artifact is present
        $configArtifact = $inputArtifacts->firstWhere('name', 'Process Config');
        $this->assertNotNull($configArtifact, 'Process Config artifact should be attached');

        // Verify the page artifact is present
        $this->assertTrue(
            $inputArtifacts->contains('id', $pageArtifact->id),
            'Page artifact should be attached as input'
        );
    }

    #[Test]
    public function create_extract_identity_processes_demonstrates_stale_cache_behavior(): void
    {
        // This test demonstrates the stale relationship cache behavior in Laravel.
        // When accessing ->children property after it's been loaded, Laravel returns
        // the cached collection even if the underlying data has changed.
        //
        // The fix is to use ->children()->get() which always queries the database.

        // Create parent artifact
        $parentArtifact = Artifact::factory()->create([
            'parent_artifact_id' => null,
        ]);

        // Create child artifact WITHOUT classification
        $childArtifact = Artifact::factory()->create([
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => [],
        ]);

        // Load children relationship (caches the stale data)
        $cachedChildren = $parentArtifact->children;
        $this->assertEmpty($cachedChildren->first()->meta, 'Initially no classification');

        // Update child with classification
        $childArtifact->meta = ['classification' => ['test' => true]];
        $childArtifact->save();

        // Demonstrate the bug: ->children property returns STALE data
        $staleChildren = $parentArtifact->children;
        $this->assertEmpty(
            $staleChildren->first()->meta,
            'BUG: ->children property returns stale cached data without classification'
        );

        // Demonstrate the fix: ->children()->get() returns FRESH data
        $freshChildren = $parentArtifact->children()->get();
        $this->assertTrue(
            $freshChildren->first()->meta['classification']['test'],
            'FIX: ->children()->get() returns fresh data with classification'
        );
    }

    #[Test]
    public function create_extract_remaining_processes_attaches_input_artifacts(): void
    {
        // BUG: createExtractRemainingProcesses() creates TaskProcesses but does NOT attach inputArtifacts.
        // Compare with createExtractIdentityProcesses() which correctly attaches inputArtifacts.
        // This test should FAIL until the bug is fixed.

        // Given: TaskRun with TaskDefinition that has an agent and schema
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create parent output artifact (document-level)
        $parentArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'parent_artifact_id' => null,
        ]);

        // Create child page artifact with classification matching a "remaining" group
        // The classification key should match the snake_case of the remaining group's key
        $pageArtifact = Artifact::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'parent_artifact_id' => $parentArtifact->id,
            'meta'               => ['classification' => ['billing_info' => true]],
        ]);

        // Create resolved TeamObject that will be used for extraction
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand Object',
        ]);

        // Create TaskRun with resolved objects stored in meta
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'resolved_objects' => [
                    'Demand' => [
                        0 => [$teamObject->id],
                    ],
                ],
            ],
        ]);

        // Attach parent artifact as output artifact of task run
        $taskRun->outputArtifacts()->attach($parentArtifact->id);

        // Extraction plan with:
        // - Level 0
        // - An identity entry (for resolved object - required by the structure)
        // - A remaining group that matches the classification key
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'            => 'Demand Identification',
                            'object_type'     => 'Demand',
                            'identity_fields' => ['name', 'amount'],
                        ],
                    ],
                    'remaining'  => [
                        [
                            'name'        => 'Billing Info',
                            'object_type' => 'Demand',
                            'fields'      => ['billing_code'],
                            'search_mode' => 'exhaustive',
                            'key'         => 'billing_info', // This matches the classification key
                        ],
                    ],
                ],
            ],
        ];

        // When: Creating extract remaining processes
        $processes = $this->orchestrator->createExtractRemainingProcesses($taskRun, $plan, 0);

        // Then: At least one Extract Remaining process should be created
        $this->assertCount(1, $processes, 'Expected 1 Extract Remaining process');
        $this->assertEquals(
            ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
            $processes[0]->operation
        );

        // BUG ASSERTION: The created process should have inputArtifacts attached
        // This will FAIL because createExtractRemainingProcesses() does not attach inputArtifacts
        // (unlike createExtractIdentityProcesses() which does)
        $inputArtifactCount = $processes[0]->inputArtifacts()->count();
        $this->assertGreaterThan(
            0,
            $inputArtifactCount,
            'Expected Extract Remaining process to have inputArtifacts attached. ' .
            'BUG: createExtractRemainingProcesses() does not attach inputArtifacts like createExtractIdentityProcesses() does.'
        );
    }

    // =========================================================================
    // resolveSearchMode() tests
    // =========================================================================

    #[Test]
    public function resolveSearchMode_returns_skim_when_global_mode_is_skim_only(): void
    {
        // Given: TaskRun with global_search_mode = skim_only
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_runner_config' => [
                'global_search_mode' => 'skim_only',
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Group wants exhaustive mode
        $group = [
            'name'        => 'Test Group',
            'search_mode' => 'exhaustive',
        ];

        // When: Resolving search mode
        $result = $this->orchestrator->resolveSearchMode($taskRun, $group);

        // Then: Returns skim (global override)
        $this->assertEquals('skim', $result);
    }

    #[Test]
    public function resolveSearchMode_returns_exhaustive_when_global_mode_is_exhaustive_only(): void
    {
        // Given: TaskRun with global_search_mode = exhaustive_only
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_runner_config' => [
                'global_search_mode' => 'exhaustive_only',
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Group wants skim mode
        $group = [
            'name'        => 'Test Group',
            'search_mode' => 'skim',
        ];

        // When: Resolving search mode
        $result = $this->orchestrator->resolveSearchMode($taskRun, $group);

        // Then: Returns exhaustive (global override)
        $this->assertEquals('exhaustive', $result);
    }

    #[Test]
    public function resolveSearchMode_uses_group_mode_when_intelligent(): void
    {
        // Given: TaskRun with global_search_mode = intelligent (or not set, which defaults to intelligent)
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_runner_config' => [
                'global_search_mode' => 'intelligent',
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Test with skim group
        $skimGroup = [
            'name'        => 'Skim Group',
            'search_mode' => 'skim',
        ];

        $this->assertEquals('skim', $this->orchestrator->resolveSearchMode($taskRun, $skimGroup));

        // Test with exhaustive group
        $exhaustiveGroup = [
            'name'        => 'Exhaustive Group',
            'search_mode' => 'exhaustive',
        ];

        $this->assertEquals('exhaustive', $this->orchestrator->resolveSearchMode($taskRun, $exhaustiveGroup));
    }

    #[Test]
    public function resolveSearchMode_defaults_to_skim_when_no_group_mode(): void
    {
        // Given: TaskRun with intelligent mode (default)
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_runner_config' => [
                'global_search_mode' => 'intelligent',
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Group without search_mode specified
        $group = [
            'name' => 'Test Group',
            // No search_mode key
        ];

        // When: Resolving search mode
        $result = $this->orchestrator->resolveSearchMode($taskRun, $group);

        // Then: Returns skim (default)
        $this->assertEquals('skim', $result);
    }

    #[Test]
    public function resolveSearchMode_defaults_to_intelligent_when_no_global_config(): void
    {
        // Given: TaskRun without global_search_mode in config
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'task_runner_config' => [], // Empty config
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Group with explicit exhaustive mode
        $group = [
            'name'        => 'Test Group',
            'search_mode' => 'exhaustive',
        ];

        // When: Resolving search mode
        $result = $this->orchestrator->resolveSearchMode($taskRun, $group);

        // Then: Returns group's mode (intelligent is default, so group setting is used)
        $this->assertEquals('exhaustive', $result);
    }
}
