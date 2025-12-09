<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\TaskDefinition;
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
                        'resolution_complete'     => true,
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
                        'resolution_complete'     => true,
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
                        'resolution_complete' => true,
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
                        'resolution_complete' => true,
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
    public function createClassificationProcess_creates_process_with_correct_operation(): void
    {
        // Given: TaskRun with extraction plan
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        $plan = [
            'levels' => [
                [
                    'level'  => 0,
                    'groups' => [
                        ['name' => 'Test Group'],
                    ],
                ],
            ],
        ];

        // When: Creating classification process (no level parameter)
        $process = $this->orchestrator->createClassificationProcess($taskRun, $plan);

        // Then: Process is created with correct operation (no level in meta)
        $this->assertNotNull($process);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_CLASSIFY, $process->operation);
        $this->assertArrayNotHasKey('level', $process->meta);
        $this->assertTrue($process->is_ready);
    }

    #[Test]
    public function createResolveObjectsProcess_creates_process_with_parent_ids(): void
    {
        // Given: TaskRun with resolved objects at level 0
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
            'meta'               => [
                'resolved_objects' => [
                    'Client' => [
                        0 => [1, 2], // Parent object IDs
                    ],
                ],
            ],
        ]);

        $plan = [
            'levels' => [
                ['level' => 0],
                ['level' => 1],
            ],
        ];

        // When: Creating resolve objects process for level 1
        $process = $this->orchestrator->createResolveObjectsProcess($taskRun, $plan, 1);

        // Then: Process is created with parent object IDs
        $this->assertNotNull($process);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS, $process->operation);
        $this->assertEquals(1, $process->meta['level']);
        $this->assertArrayHasKey('parent_object_ids', $process->meta);
        $this->assertContains(1, $process->meta['parent_object_ids']);
        $this->assertContains(2, $process->meta['parent_object_ids']);
    }

    #[Test]
    public function createExtractGroupProcesses_creates_process_for_each_resolved_object(): void
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
                    'level'  => 0,
                    'groups' => [
                        [
                            'name'    => 'Client Details',
                            'objects' => [
                                [
                                    'object_type'       => 'Client',
                                    'is_identification' => false,
                                    'fragment_selector' => [
                                        'children' => [
                                            'address' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When: Creating extract group processes
        $processes = $this->orchestrator->createExtractGroupProcesses($taskRun, $plan, 0);

        // Then: Process created for each resolved object
        $this->assertCount(2, $processes);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_GROUP, $processes[0]->operation);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_GROUP, $processes[1]->operation);

        $objectIds = array_column(array_map(fn($p) => $p->meta, $processes), 'object_id');
        $this->assertContains($client1->id, $objectIds);
        $this->assertContains($client2->id, $objectIds);
    }

    #[Test]
    public function createExtractGroupProcesses_skips_identification_groups(): void
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
                    'level'  => 0,
                    'groups' => [
                        [
                            'name'    => 'Client ID',
                            'objects' => [
                                [
                                    'object_type'       => 'Client',
                                    'is_identification' => true, // Identification group
                                    'fragment_selector' => [
                                        'children' => [
                                            'name' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When: Creating extract group processes
        $processes = $this->orchestrator->createExtractGroupProcesses($taskRun, $plan, 0);

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
    public function createClassifyProcessesPerPage_creates_process_for_each_page(): void
    {
        // Given: TaskRun and page data
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        $pages = [
            ['artifact_id' => 1, 'file_id' => 10, 'page_number' => 1],
            ['artifact_id' => 2, 'file_id' => 11, 'page_number' => 2],
            ['artifact_id' => 3, 'file_id' => 12, 'page_number' => 3],
        ];

        // When: Creating classify processes per page
        $processes = $this->orchestrator->createClassifyProcessesPerPage($taskRun, $pages);

        // Then: Process created for each page
        $this->assertCount(3, $processes);

        // Verify first process
        $this->assertEquals('Classify Page 1', $processes[0]->name);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_CLASSIFY, $processes[0]->operation);
        $this->assertEquals('Classifying page 1', $processes[0]->activity);
        $this->assertEquals(1, $processes[0]->meta['artifact_id']);
        $this->assertEquals(10, $processes[0]->meta['file_id']);
        $this->assertEquals(1, $processes[0]->meta['page_number']);
        $this->assertTrue($processes[0]->is_ready);

        // Verify second process
        $this->assertEquals('Classify Page 2', $processes[1]->name);
        $this->assertEquals(2, $processes[1]->meta['page_number']);

        // Verify third process
        $this->assertEquals('Classify Page 3', $processes[2]->name);
        $this->assertEquals(3, $processes[2]->meta['page_number']);
    }

    #[Test]
    public function createClassifyProcessesPerPage_skips_pages_without_page_number(): void
    {
        // Given: TaskRun and page data with missing page_number
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        $pages = [
            ['artifact_id' => 1, 'file_id' => 10, 'page_number' => 1],
            ['artifact_id' => 2, 'file_id' => 11], // Missing page_number
            ['artifact_id' => 3, 'file_id' => 12, 'page_number' => 3],
        ];

        // When: Creating classify processes per page
        $processes = $this->orchestrator->createClassifyProcessesPerPage($taskRun, $pages);

        // Then: Only 2 processes created (skipped the one without page_number)
        $this->assertCount(2, $processes);
        $this->assertEquals(1, $processes[0]->meta['page_number']);
        $this->assertEquals(3, $processes[1]->meta['page_number']);
    }

    #[Test]
    public function isClassificationComplete_returns_true_when_all_classify_processes_complete(): void
    {
        // Given: TaskRun with all classify processes completed
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        // Create completed classify processes
        $taskRun->taskProcesses()->create([
            'name'         => 'Classify Page 1',
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'activity'     => 'Classifying page 1',
            'meta'         => ['page_number' => 1],
            'is_ready'     => true,
            'completed_at' => now(),
        ]);

        $taskRun->taskProcesses()->create([
            'name'         => 'Classify Page 2',
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'activity'     => 'Classifying page 2',
            'meta'         => ['page_number' => 2],
            'is_ready'     => true,
            'completed_at' => now(),
        ]);

        // When: Checking if classification is complete
        $isComplete = $this->orchestrator->isClassificationComplete($taskRun);

        // Then: Returns true
        $this->assertTrue($isComplete);
    }

    #[Test]
    public function isClassificationComplete_returns_false_when_some_classify_processes_incomplete(): void
    {
        // Given: TaskRun with some classify processes incomplete
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        // One completed
        $taskRun->taskProcesses()->create([
            'name'         => 'Classify Page 1',
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'activity'     => 'Classifying page 1',
            'meta'         => ['page_number' => 1],
            'is_ready'     => true,
            'completed_at' => now(),
        ]);

        // One incomplete
        $taskRun->taskProcesses()->create([
            'name'      => 'Classify Page 2',
            'operation' => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'activity'  => 'Classifying page 2',
            'meta'      => ['page_number' => 2],
            'is_ready'  => true,
            // No completed_at - still in progress
        ]);

        // When: Checking if classification is complete
        $isComplete = $this->orchestrator->isClassificationComplete($taskRun);

        // Then: Returns false
        $this->assertFalse($isComplete);
    }

    #[Test]
    public function isClassificationComplete_returns_false_when_no_classify_processes_exist(): void
    {
        // Given: TaskRun with no classify processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        // When: Checking if classification is complete
        $isComplete = $this->orchestrator->isClassificationComplete($taskRun);

        // Then: Returns false (edge case - no processes means not complete)
        $this->assertFalse($isComplete);
    }

    #[Test]
    public function isClassificationComplete_ignores_non_classify_processes(): void
    {
        // Given: TaskRun with classify processes and other operation processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id])->id,
        ]);

        // Completed classify process
        $taskRun->taskProcesses()->create([
            'name'         => 'Classify Page 1',
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'activity'     => 'Classifying page 1',
            'meta'         => ['page_number' => 1],
            'is_ready'     => true,
            'completed_at' => now(),
        ]);

        // Incomplete process with different operation (should be ignored)
        $taskRun->taskProcesses()->create([
            'name'      => 'Extract Group',
            'operation' => ExtractDataTaskRunner::OPERATION_EXTRACT_GROUP,
            'activity'  => 'Extracting data',
            'meta'      => ['level' => 0],
            'is_ready'  => true,
            // No completed_at
        ]);

        // When: Checking if classification is complete
        $isComplete = $this->orchestrator->isClassificationComplete($taskRun);

        // Then: Returns true (only classify processes are considered)
        $this->assertTrue($isComplete);
    }
}
