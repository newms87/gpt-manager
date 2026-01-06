<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\DataExtraction\PlanningPhaseService;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class PlanningPhaseServiceTest extends AuthenticatedTestCase
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

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'name'                 => 'Extract Data Test',
            'task_runner_name'     => ExtractDataTaskRunner::RUNNER_NAME,
            'schema_definition_id' => $this->schemaDefinition->id,
            'agent_id'             => $this->agent->id,
        ]);
    }

    #[Test]
    public function handlePlanningPhaseIfActive_returns_false_when_no_planning_processes_exist(): void
    {
        // Given: TaskRun with no planning processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // When: Checking if planning phase is active
        $result = app(PlanningPhaseService::class)->handlePlanningPhaseIfActive($taskRun);

        // Then: Returns false since not in planning phase
        $this->assertFalse($result);
    }

    #[Test]
    public function handlePlanningPhaseIfActive_returns_true_when_planning_not_complete(): void
    {
        // Given: TaskRun with incomplete Plan:Identify processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY,
            'meta'         => ['object_type' => 'Client'],
            'started_at'   => now(),
            'completed_at' => null, // Not complete
        ]);

        // When: Checking if planning phase is active
        $result = app(PlanningPhaseService::class)->handlePlanningPhaseIfActive($taskRun);

        // Then: Returns true since planning is still in progress
        $this->assertTrue($result);
    }

    #[Test]
    public function handlePlanningPhaseIfActive_returns_false_when_classification_already_exists(): void
    {
        // Given: TaskRun with completed planning AND classification already exists
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create completed Plan:Identify process
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY,
            'meta'         => ['object_type' => 'Client'],
            'started_at'   => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        // Create completed Classify processes (classification already ran)
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'         => ['child_artifact_id' => 1],
            'started_at'   => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(2),
        ]);

        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'         => ['child_artifact_id' => 2],
            'started_at'   => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(1),
        ]);

        // When: Checking if planning phase is active
        // This is the BUG: It currently returns TRUE even though planning is done
        // and classification already started, preventing extraction phase from running
        $result = app(PlanningPhaseService::class)->handlePlanningPhaseIfActive($taskRun);

        // Then: Should return false since planning is complete AND classification exists
        // This allows afterAllProcessesCompleted to proceed to handleExtractionPhase
        $this->assertFalse(
            $result,
            'handlePlanningPhaseIfActive should return false when planning is complete AND classification already exists'
        );
    }

    #[Test]
    public function handlePlanningPhaseIfActive_returns_false_when_planning_and_remaining_complete_and_classification_exists(): void
    {
        // Given: TaskRun with completed planning (both identity and remaining) AND classification already exists
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'meta'               => [],
        ]);

        // Create completed Plan:Identify process
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY,
            'meta'         => ['object_type' => 'Client'],
            'started_at'   => now()->subMinutes(15),
            'completed_at' => now()->subMinutes(10),
        ]);

        // Create completed Plan:Remaining process
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_PLAN_REMAINING,
            'meta'         => ['object_type' => 'Client'],
            'started_at'   => now()->subMinutes(9),
            'completed_at' => now()->subMinutes(5),
        ]);

        // Create completed Classify processes (classification already ran)
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'meta'         => ['child_artifact_id' => 1],
            'started_at'   => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(2),
        ]);

        // When: Checking if planning phase is active
        $result = app(PlanningPhaseService::class)->handlePlanningPhaseIfActive($taskRun);

        // Then: Should return false since planning is complete AND classification exists
        $this->assertFalse(
            $result,
            'handlePlanningPhaseIfActive should return false when all planning is complete AND classification already exists'
        );
    }
}
