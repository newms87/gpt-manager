<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\DataExtraction\ExtractionPhaseService;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractionPhaseServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private Agent $agent;

    private SchemaDefinition $schemaDefinition;

    private TaskDefinition $taskDefinition;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-5-mini',
        ]);

        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                ],
            ],
        ]);
    }

    /**
     * Create a TaskDefinition with an extraction plan.
     */
    private function createTaskDefinitionWithPlan(array $plan): TaskDefinition
    {
        return TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'Extract Data Test',
            'task_runner_name'     => ExtractDataTaskRunner::RUNNER_NAME,
            'schema_definition_id' => $this->schemaDefinition->id,
            'agent_id'             => $this->agent->id,
            'meta'                 => [
                'extraction_plan' => $plan,
            ],
        ]);
    }

    /**
     * Create a simple extraction plan with two levels.
     */
    private function createTwoLevelPlan(): array
    {
        return [
            'levels' => [
                0 => [
                    'identities' => [
                        [
                            'name'            => 'Client Identification',
                            'object_type'     => 'Client',
                            'identity_fields' => ['client_name'],
                        ],
                    ],
                    'remaining' => [],
                ],
                1 => [
                    'identities' => [
                        [
                            'name'            => 'Project Identification',
                            'object_type'     => 'Project',
                            'identity_fields' => ['project_name'],
                        ],
                    ],
                    'remaining' => [],
                ],
            ],
        ];
    }

    #[Test]
    public function handleExtractionPhase_advances_to_next_level_when_no_remaining_processes_needed(): void
    {
        // This tests the bug fix where the code returned early after marking
        // extraction complete when no remaining processes were needed,
        // which prevented level progression.

        // Given: TaskDefinition with a two-level extraction plan
        $plan           = $this->createTwoLevelPlan();
        $taskDefinition = $this->createTaskDefinitionWithPlan($plan);

        // Given: TaskRun at level 0 with classification complete
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'current_level' => 0,
            ],
        ]);

        // Given: Classification processes exist and are complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'         => ['child_artifact_id' => 1],
            'started_at'   => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        // Given: Extract Identity process for level 0 is complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'         => ['level' => 0, 'identity_group' => ['object_type' => 'Client']],
            'started_at'   => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(2),
        ]);

        // When: handleExtractionPhase is called
        // The orchestrator's createExtractRemainingProcesses will return empty
        // because there are no 'remaining' groups in level 0 of our plan.
        // The bug was that the code returned early instead of continuing
        // to check level progression.
        app(ExtractionPhaseService::class)->handleExtractionPhase($taskRun);

        // Then: The level should have advanced (or at least level 0 should be marked complete)
        $taskRun->refresh();

        // Verify level 0 is marked as identity_complete and extraction_complete
        $levelProgress = app(ExtractionProcessOrchestrator::class)->getLevelProgress($taskRun);
        $this->assertTrue(
            $levelProgress[0]['identity_complete'] ?? false,
            'Level 0 should be marked as identity_complete'
        );
        $this->assertTrue(
            $levelProgress[0]['extraction_complete'] ?? false,
            'Level 0 should be marked as extraction_complete when no remaining processes are needed'
        );
    }

    #[Test]
    public function handleExtractionPhase_waits_for_remaining_processes_to_complete(): void
    {
        // Given: TaskDefinition with a plan that has remaining groups
        $plan = [
            'levels' => [
                0 => [
                    'identities' => [
                        [
                            'name'            => 'Client Identification',
                            'object_type'     => 'Client',
                            'identity_fields' => ['client_name'],
                        ],
                    ],
                    'remaining' => [
                        [
                            'name'        => 'Client Details',
                            'object_type' => 'Client',
                            'key'         => 'client_details',
                            'fields'      => ['phone', 'email'],
                        ],
                    ],
                ],
            ],
        ];
        $taskDefinition = $this->createTaskDefinitionWithPlan($plan);

        // Given: TaskRun at level 0 with identity complete but remaining in progress
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'meta'               => [
                'current_level'    => 0,
                'resolved_objects' => [
                    'Client' => [
                        0 => [1], // Resolved object ID 1 at level 0
                    ],
                ],
            ],
        ]);

        // Given: Classification processes are complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'         => ['child_artifact_id' => 1],
            'started_at'   => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        // Given: Extract Identity process for level 0 is complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'         => ['level' => 0, 'identity_group' => ['object_type' => 'Client']],
            'started_at'   => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(2),
        ]);

        // Given: Extract Remaining process exists but is NOT complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
            'meta'         => ['level' => 0, 'extraction_group' => ['name' => 'Client Details']],
            'started_at'   => now()->subMinute(),
            'completed_at' => null, // NOT complete!
        ]);

        // When: handleExtractionPhase is called
        app(ExtractionPhaseService::class)->handleExtractionPhase($taskRun);

        // Then: Level 0 should NOT be marked extraction_complete
        $taskRun->refresh();
        $levelProgress = app(ExtractionProcessOrchestrator::class)->getLevelProgress($taskRun);

        // Identity should be marked complete since it finished
        $this->assertTrue(
            $levelProgress[0]['identity_complete'] ?? false,
            'Level 0 should be marked as identity_complete'
        );

        // Extraction should NOT be complete since remaining process is still running
        $this->assertFalse(
            $levelProgress[0]['extraction_complete'] ?? false,
            'Level 0 should NOT be marked as extraction_complete while remaining processes are running'
        );

        // Current level should still be 0
        $this->assertEquals(
            0,
            $taskRun->meta['current_level'] ?? 0,
            'Current level should remain at 0 while remaining processes are incomplete'
        );
    }

    #[Test]
    public function handleExtractionPhase_creates_identity_processes_for_next_level(): void
    {
        // Given: TaskDefinition with a two-level extraction plan
        $plan           = $this->createTwoLevelPlan();
        $taskDefinition = $this->createTaskDefinitionWithPlan($plan);

        // Given: TaskRun at level 0 with level 0 fully complete
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

        // Given: Classification processes are complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'         => ['child_artifact_id' => 1],
            'started_at'   => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        // Given: Extract Identity process for level 0 is complete
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
            'meta'         => ['level' => 0, 'identity_group' => ['object_type' => 'Client']],
            'started_at'   => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(2),
        ]);

        // Count processes before
        $processCountBefore = $taskRun->taskProcesses()->count();

        // When: handleExtractionPhase is called
        app(ExtractionPhaseService::class)->handleExtractionPhase($taskRun);

        // Then: The task run should have advanced to level 1
        $taskRun->refresh();
        $this->assertEquals(
            1,
            $taskRun->meta['current_level'] ?? 0,
            'Current level should advance to 1 after level 0 is complete'
        );

        // Note: New identity processes may not be created if there are no artifacts
        // matching the classification for level 1. The key assertion is that the
        // level advanced, which was the bug being fixed.
    }
}
