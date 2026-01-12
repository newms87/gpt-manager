<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\DataExtraction\ClassificationOrchestrator;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ClassificationOrchestratorTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ClassificationOrchestrator $orchestrator;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->orchestrator = app(ClassificationOrchestrator::class);
    }

    #[Test]
    public function createClassifyProcessesPerPage_creates_process_for_each_child_artifact(): void
    {
        // Given: TaskRun and child artifacts
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun        = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);

        // Create parent artifact
        $parentArtifact = Artifact::create([
            'name'               => 'Parent',
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'team_id'            => $this->user->currentTeam->id,
        ]);

        // Create child artifacts
        $child1 = Artifact::create([
            'name'                => 'Page 1',
            'parent_artifact_id'  => $parentArtifact->id,
            'position'            => 1,
            'task_definition_id'  => $taskDefinition->id,
            'task_run_id'         => $taskRun->id,
            'team_id'             => $this->user->currentTeam->id,
        ]);

        $child2 = Artifact::create([
            'name'                => 'Page 2',
            'parent_artifact_id'  => $parentArtifact->id,
            'position'            => 2,
            'task_definition_id'  => $taskDefinition->id,
            'task_run_id'         => $taskRun->id,
            'team_id'             => $this->user->currentTeam->id,
        ]);

        $childArtifacts = new Collection([$child1, $child2]);

        // Sample boolean schema for classification
        $booleanSchema = [
            'type'       => 'object',
            'properties' => [
                'has_diagnosis' => ['type' => 'boolean', 'description' => 'Page contains diagnosis codes'],
                'has_billing'   => ['type' => 'boolean', 'description' => 'Page contains billing info'],
            ],
        ];

        // When: Creating classify processes per page
        $processes = $this->orchestrator->createClassifyProcessesPerPage($taskRun, $childArtifacts, $booleanSchema);

        // Then: Process created for each child artifact
        $this->assertCount(2, $processes);

        // Verify first process
        $this->assertEquals('Classify Page 1', $processes[0]->name);
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_CLASSIFY, $processes[0]->operation);
        $this->assertEquals('Classifying page 1', $processes[0]->activity);
        $this->assertEquals($child1->id, $processes[0]->meta['child_artifact_id']);
        $this->assertTrue($processes[0]->is_ready);

        // Verify second process
        $this->assertEquals('Classify Page 2', $processes[1]->name);
        $this->assertEquals($child2->id, $processes[1]->meta['child_artifact_id']);

        // Verify input artifacts are attached
        $this->assertEquals($child1->id, $processes[0]->inputArtifacts->first()->id);
        $this->assertEquals($child2->id, $processes[1]->inputArtifacts->first()->id);
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
            'name'      => 'Extract Identity',
            'operation' => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'activity'  => 'Extracting identity data',
            'meta'      => ['level' => 0],
            'is_ready'  => true,
            // No completed_at
        ]);

        // When: Checking if classification is complete
        $isComplete = $this->orchestrator->isClassificationComplete($taskRun);

        // Then: Returns true (only classify processes are considered)
        $this->assertTrue($isComplete);
    }

    #[Test]
    public function getGroupsAtLevel_returns_combined_identities_and_remaining(): void
    {
        // Given: Extraction plan with identities and remaining groups
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'object_type'       => 'Patient',
                            'identity_fields'   => ['name'],
                            'fragment_selector' => [],
                        ],
                    ],
                    'remaining'  => [
                        [
                            'name'              => 'Medical Records',
                            'description'       => 'Medical treatment documentation',
                            'fragment_selector' => [],
                        ],
                    ],
                ],
            ],
        ];

        // When: Getting groups at level 0
        $groups = $this->orchestrator->getGroupsAtLevel($plan, 0);

        // Then: Returns combined identities and remaining
        $this->assertCount(2, $groups);
        $this->assertEquals('Patient', $groups[0]['object_type']);
        $this->assertEquals('Medical Records', $groups[1]['name']);
    }

    #[Test]
    public function getGroupsAtLevel_returns_empty_array_for_nonexistent_level(): void
    {
        // Given: Extraction plan with only level 0
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [],
                    'remaining'  => [],
                ],
            ],
        ];

        // When: Getting groups at level 999
        $groups = $this->orchestrator->getGroupsAtLevel($plan, 999);

        // Then: Returns empty array
        $this->assertEmpty($groups);
    }

    #[Test]
    public function getGroupsAtLevel_handles_missing_identities_or_remaining(): void
    {
        // Given: Extraction plan with only identities at level 0
        $plan = [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        ['object_type' => 'Patient', 'fragment_selector' => []],
                    ],
                    // No remaining key
                ],
            ],
        ];

        // When: Getting groups at level 0
        $groups = $this->orchestrator->getGroupsAtLevel($plan, 0);

        // Then: Returns only identities
        $this->assertCount(1, $groups);
        $this->assertEquals('Patient', $groups[0]['object_type']);
    }

    #[Test]
    public function createClassifyProcessesPerPage_skips_artifacts_with_cached_results(): void
    {
        // Given: TaskRun with child artifacts, one with cached classification
        $taskDefinition = TaskDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun        = TaskRun::factory()->create(['task_definition_id' => $taskDefinition->id]);

        // Create parent artifact
        $parentArtifact = Artifact::create([
            'name'               => 'Parent',
            'task_definition_id' => $taskDefinition->id,
            'task_run_id'        => $taskRun->id,
            'team_id'            => $this->user->currentTeam->id,
        ]);

        // Schema for classification
        $booleanSchema = [
            'type'       => 'object',
            'properties' => [
                'has_diagnosis' => ['type' => 'boolean'],
            ],
        ];

        // Create cached StoredFile for first child
        $cachedStoredFile = \Newms87\Danx\Models\Utilities\StoredFile::create([
            'filename'  => 'page1.pdf',
            'mime_type' => 'application/pdf',
            'size'      => 1024,
            'disk'      => 'local',
            'filepath'  => 'test/page1.pdf',
            'meta'      => [
                'classifications' => [
                    hash('sha256', json_encode($booleanSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) => [
                        'schema_hash'   => hash('sha256', json_encode($booleanSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        'classified_at' => now()->toIso8601String(),
                        'result'        => ['has_diagnosis' => true],
                    ],
                ],
            ],
        ]);

        // Create child artifacts
        $child1 = Artifact::create([
            'name'                => 'Page 1',
            'parent_artifact_id'  => $parentArtifact->id,
            'position'            => 1,
            'task_definition_id'  => $taskDefinition->id,
            'task_run_id'         => $taskRun->id,
            'team_id'             => $this->user->currentTeam->id,
        ]);
        $child1->storedFiles()->attach($cachedStoredFile->id);

        $child2 = Artifact::create([
            'name'                => 'Page 2',
            'parent_artifact_id'  => $parentArtifact->id,
            'position'            => 2,
            'task_definition_id'  => $taskDefinition->id,
            'task_run_id'         => $taskRun->id,
            'team_id'             => $this->user->currentTeam->id,
        ]);

        $childArtifacts = new Collection([$child1, $child2]);

        // When: Creating classify processes per page
        $processes = $this->orchestrator->createClassifyProcessesPerPage($taskRun, $childArtifacts, $booleanSchema);

        // Then: Only one process created (second page without cache)
        $this->assertCount(1, $processes);
        $this->assertEquals('Classify Page 2', $processes[0]->name);
        $this->assertEquals($child2->id, $processes[0]->meta['child_artifact_id']);

        // Verify cached result was stored in artifact meta
        $child1->refresh();
        $this->assertArrayHasKey('classification', $child1->meta);
        $this->assertEquals(['has_diagnosis' => true], $child1->meta['classification']);
    }
}
