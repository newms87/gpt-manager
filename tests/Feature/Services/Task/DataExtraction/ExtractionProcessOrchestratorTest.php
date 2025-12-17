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
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'level_progress' => [
                    0 => [
                        'classification_complete' => true,
                        'identity_complete'       => true,
                        'extraction_complete'     => false, // Not complete!
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
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'level_progress' => [
                    0 => [
                        'classification_complete' => true,
                        'identity_complete'       => true,
                        'extraction_complete'     => true,
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
        $this->assertArrayHasKey('identity_group', $processes[0]->meta);

        // Verify input artifacts are attached
        $this->assertGreaterThan(0, $processes[0]->inputArtifacts()->count());
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
}
