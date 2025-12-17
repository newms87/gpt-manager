<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Support\Facades\Queue;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

/**
 * Integration test for complete ExtractDataTaskRunner flow.
 *
 * Tests the COMPLETE extraction process state management and TeamObject creation
 * across multiple levels, WITHOUT making actual LLM calls.
 *
 * This test verifies:
 * - Plan is cached and retrieved correctly
 * - Level progress tracking works
 * - Process orchestration creates correct processes at each phase
 * - State transitions work correctly
 */
class ExtractDataTaskRunnerIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractDataTaskRunner $runner;

    private Agent $agent;

    private SchemaDefinition $schemaDefinition;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    private ExtractionProcessOrchestrator $orchestrator;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Create agent
        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-5-mini',
        ]);

        // Create schema definition for Demand Letter extraction
        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Demand Letter Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name'   => ['type' => 'string'],
                    'accident_date' => ['type' => 'string', 'format' => 'date'],
                    'total_damages' => ['type' => 'number'],
                    'injuries'      => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'body_part' => ['type' => 'string'],
                                'severity'  => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Create task definition with pre-cached plan
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'Extract Demand Letter Data',
            'task_runner_name'     => ExtractDataTaskRunner::RUNNER_NAME,
            'schema_definition_id' => $this->schemaDefinition->id,
            'agent_id'             => $this->agent->id,
            'task_runner_config'   => [
                'confidence_threshold' => 3,
                'skim_batch_size'      => 5,
            ],
            'meta' => [],
        ]);

        // Set the extraction plan with valid cache key
        $plan     = $this->getExtractionPlan();
        $cacheKey = hash('sha256', json_encode([
            'schema'              => $this->schemaDefinition->schema,
            'user_planning_hints' => null,
            'global_search_mode'  => 'intelligent',
            'group_max_points'    => 10,
        ]));

        $this->taskDefinition->meta = [
            'extraction_plan'           => $plan,
            'extraction_plan_cache_key' => $cacheKey,
        ];
        $this->taskDefinition->save();

        $this->runner       = new ExtractDataTaskRunner();
        $this->orchestrator = app(ExtractionProcessOrchestrator::class);
    }

    #[Test]
    public function complete_extraction_flow_orchestrates_processes_correctly(): void
    {
        // GIVEN: TaskRun with pre-cached plan and classified artifacts
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create 3 input artifacts with stored files and classification
        $this->createClassifiedArtifacts(3);

        // STEP 1: prepareRun validates schema definition
        $this->runner->setTaskRun($this->taskRun);
        $this->runner->prepareRun();

        // STEP 2: Simulate Default Task process creation (done by TaskRunnerService in real flow)
        $defaultTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'operation'   => 'Default Task', // BaseTaskRunner::OPERATION_DEFAULT
            'activity'    => 'Starting data extraction',
            'meta'        => [],
            'is_ready'    => true,
            'started_at'  => now(),
        ]);

        // STEP 3: Run default task (initialize operation) to create per-page classification processes
        // Fake the queue to prevent sync execution of dispatched jobs (LLM calls)
        Queue::fake();

        $this->runner->setTaskRun($this->taskRun)->setTaskProcess($defaultTaskProcess);
        $this->runner->run();

        $classificationProcesses = $this->taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->get();

        $this->assertGreaterThan(0, $classificationProcesses->count(), 'Default Task should create per-page classification processes');

        // Verify each process has required meta fields
        foreach ($classificationProcesses as $process) {
            $this->assertArrayHasKey('child_artifact_id', $process->meta);

            // Verify input artifacts are attached
            $this->assertGreaterThan(0, $process->inputArtifacts->count());
        }

        // Verify default task process completed
        $defaultTaskProcess->refresh();
        $this->assertNotNull($defaultTaskProcess->completed_at);

        // STEP 4: Simulate ALL classification processes completing
        foreach ($classificationProcesses as $process) {
            $process->update([
                'started_at'   => now()->subMinutes(2),
                'completed_at' => now(),
            ]);
        }

        // afterAllProcessesCompleted creates Extract Identity processes for level 0
        $this->runner->afterAllProcessesCompleted();

        $identityProcess0 = $this->taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->where('meta->level', 0)
            ->first();

        $this->assertNotNull($identityProcess0, 'Should create Extract Identity process for level 0');
        $this->assertEquals(0, $identityProcess0->meta['level']);

        // STEP 5: Simulate creating Demand object at level 0 and completing identity extraction
        $demand = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'John Smith Demand',
        ]);

        // Store resolved object in TaskRun.meta
        $this->orchestrator->storeResolvedObjectId($this->taskRun, 'Demand', $demand->id, 0);

        $identityProcess0->update([
            'started_at'   => now()->subMinutes(1),
            'completed_at' => now(),
        ]);

        // Mark identity complete
        $this->orchestrator->updateLevelProgress($this->taskRun, 0, 'identity_complete', true);

        // STEP 6: afterAllProcessesCompleted creates Extract Remaining processes for level 0
        $this->runner->afterAllProcessesCompleted();

        $remainingProcesses = $this->taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING)
            ->where('meta->level', 0)
            ->get();

        $this->assertGreaterThan(0, $remainingProcesses->count(), 'Should create Extract Remaining processes for level 0');

        // Verify extract process has correct object_id
        $remainingProcess = $remainingProcesses->first();
        $this->assertEquals($demand->id, $remainingProcess->meta['object_id']);
        $this->assertEquals(0, $remainingProcess->meta['level']);

        // STEP 7: Simulate extraction completion (just mark processes complete)
        foreach ($remainingProcesses as $process) {
            $process->update([
                'started_at'   => now()->subMinutes(1),
                'completed_at' => now(),
            ]);
        }

        // Mark extraction complete
        $this->orchestrator->updateLevelProgress($this->taskRun, 0, 'extraction_complete', true);

        // STEP 8: Level 0 complete - should advance to level 1
        $this->runner->afterAllProcessesCompleted();

        $this->taskRun->refresh();
        $this->assertEquals(1, $this->orchestrator->getCurrentLevel($this->taskRun));

        $identityProcess1 = $this->taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->where('meta->level', 1)
            ->first();

        $this->assertNotNull($identityProcess1, 'Should create Extract Identity process for level 1');
        $this->assertEquals(1, $identityProcess1->meta['level']);

        // Verify parent object IDs passed to level 1
        $this->assertContains($demand->id, $identityProcess1->meta['parent_object_ids'] ?? []);

        // STEP 9: Simulate creating Injury objects at level 1
        $injury1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injury',
            'name'    => 'Neck Injury',
        ]);

        $injury2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Injury',
            'name'    => 'Back Injury',
        ]);

        // Store resolved objects
        $this->orchestrator->storeResolvedObjectId($this->taskRun, 'Injury', $injury1->id, 1);
        $this->orchestrator->storeResolvedObjectId($this->taskRun, 'Injury', $injury2->id, 1);

        // Mark level 1 identity complete
        $this->orchestrator->updateLevelProgress($this->taskRun, 1, 'identity_complete', true);

        $identityProcess1->update([
            'started_at'   => now()->subMinutes(1),
            'completed_at' => now(),
        ]);

        // FINAL VERIFICATION: State and objects
        $this->taskRun->refresh();

        // Verify level progress tracking
        $this->assertTrue($this->taskRun->meta['level_progress'][0]['identity_complete'] ?? false);
        $this->assertTrue($this->taskRun->meta['level_progress'][0]['extraction_complete'] ?? false);
        $this->assertTrue($this->taskRun->meta['level_progress'][1]['identity_complete'] ?? false);

        // Verify resolved objects stored correctly
        $this->assertArrayHasKey('resolved_objects', $this->taskRun->meta);
        $this->assertContains($demand->id, $this->taskRun->meta['resolved_objects']['Demand'][0] ?? []);
        $this->assertContains($injury1->id, $this->taskRun->meta['resolved_objects']['Injury'][1] ?? []);
        $this->assertContains($injury2->id, $this->taskRun->meta['resolved_objects']['Injury'][1] ?? []);

        // Verify TeamObjects created
        $this->assertEquals(1, TeamObject::where('type', 'Demand')->count());
        $this->assertEquals(2, TeamObject::where('type', 'Injury')->count());

        // Verify parent-child relationship data (parent_object_ids passed to level 1)
        $this->assertContains($demand->id, $identityProcess1->meta['parent_object_ids'] ?? []);
    }

    /**
     * Create classified artifacts with stored files
     */
    private function createClassifiedArtifacts(int $count): void
    {
        // Create parent output artifact (represents all pages)
        $parentArtifact = Artifact::factory()->create([
            'task_run_id'        => $this->taskRun->id,
            'parent_artifact_id' => null,
        ]);

        $this->taskRun->outputArtifacts()->attach($parentArtifact->id);

        // Create child artifacts (individual pages) with proper classification
        for ($i = 1; $i <= $count; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'            => $this->user->currentTeam->id,
                'task_run_id'        => $this->taskRun->id,
                'parent_artifact_id' => $parentArtifact->id,
                'name'               => "Page $i",
                'meta'               => [
                    'classification' => [
                        'demand_identification' => true,
                        'demand_damages'        => true,
                        'injury_identification' => true,
                    ],
                ],
            ]);

            $storedFile = StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);
            $this->taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $this->taskRun->outputArtifacts()->attach($artifact->id);
        }
    }

    /**
     * Get the extraction plan structure
     */
    private function getExtractionPlan(): array
    {
        return [
            'levels' => [
                [
                    'level'      => 0,
                    'identities' => [
                        [
                            'name'              => 'Demand Identification',
                            'description'       => 'Client and case info',
                            'object_type'       => 'Demand',
                            'identity_fields'   => ['client_name', 'accident_date'],
                            'search_mode'       => 'skim',
                            'fragment_selector' => [
                                'children' => [
                                    'client_name'   => ['type' => 'string'],
                                    'accident_date' => ['type' => 'string', 'format' => 'date'],
                                ],
                            ],
                        ],
                    ],
                    'remaining'  => [
                        [
                            'name'              => 'Demand Damages',
                            'description'       => 'Damage amounts',
                            'object_type'       => 'Demand',
                            'search_mode'       => 'skim',
                            'fragment_selector' => [
                                'children' => [
                                    'total_damages' => ['type' => 'number'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'level'      => 1,
                    'identities' => [
                        [
                            'name'              => 'Injuries',
                            'description'       => 'Injury details',
                            'object_type'       => 'Injury',
                            'identity_fields'   => ['body_part', 'severity'],
                            'parent_type'       => 'Demand',
                            'search_mode'       => 'exhaustive',
                            'fragment_selector' => [
                                'children' => [
                                    'body_part' => ['type' => 'string'],
                                    'severity'  => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'remaining'  => [],
                ],
            ],
        ];
    }
}
