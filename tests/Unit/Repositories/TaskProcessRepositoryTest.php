<?php

namespace Tests\Unit\Repositories;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Repositories\TaskProcessRepository;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskProcessRepositoryTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private TaskProcessRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->repository = new TaskProcessRepository();
    }

    public function test_fieldOptions_returnsOperationsAndStatusesForCurrentTeam(): void
    {
        // Given - Create task definition and run for current team
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Create task processes with different operations and statuses
        // Status is auto-computed, so we set the timestamp fields that result in desired status
        TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'operation'     => 'Initialize',
            'started_at'    => now()->subMinutes(5),
            'completed_at'  => now(), // Completed status
        ]);

        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => 'Merge',
            'started_at'   => now()->subMinutes(2), // Running status
        ]);

        TaskProcess::factory()->create([
            'task_run_id'   => $taskRun->id,
            'operation'     => 'Comparison Window',
            'started_at'    => now()->subMinutes(10),
            'completed_at'  => now()->subMinutes(5), // Completed status
        ]);

        // Create process for different team - should be excluded
        $otherTaskDefinition = TaskDefinition::factory()->create([
            'team_id' => 999999, // Different team
        ]);

        $otherTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $otherTaskDefinition->id,
        ]);

        TaskProcess::factory()->create([
            'task_run_id'  => $otherTaskRun->id,
            'operation'    => 'Other Team Operation',
            'failed_at'    => now(), // Failed status
        ]);

        // When
        $result = $this->repository->fieldOptions();

        // Then - Should return operations and statuses only from current team
        $this->assertIsArray($result);
        $this->assertArrayHasKey('operation', $result);
        $this->assertArrayHasKey('status', $result);

        // Verify operations (sorted alphabetically)
        $this->assertCount(3, $result['operation']);
        $this->assertEquals(['Comparison Window', 'Initialize', 'Merge'], $result['operation']);

        // Verify statuses (sorted alphabetically)
        $this->assertCount(2, $result['status']);
        $this->assertEquals([WorkflowStatesContract::STATUS_COMPLETED, WorkflowStatesContract::STATUS_RUNNING], $result['status']);

        // Verify other team's operation is excluded
        $this->assertNotContains('Other Team Operation', $result['operation']);
        $this->assertNotContains(WorkflowStatesContract::STATUS_FAILED, $result['status']);
    }

    public function test_fieldOptions_filtersByTaskRunId(): void
    {
        // Given - Create two task runs
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun1 = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $taskRun2 = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Task run 1 processes
        TaskProcess::factory()->create([
            'task_run_id'   => $taskRun1->id,
            'operation'     => 'Initialize',
            'started_at'    => now()->subMinutes(5),
            'completed_at'  => now(), // Completed status
        ]);

        // Task run 2 processes
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun2->id,
            'operation'    => 'Merge',
            'started_at'   => now()->subMinutes(2), // Running status
        ]);

        // When - Filter by task_run_id
        $result = $this->repository->fieldOptions(['task_run_id' => $taskRun1->id]);

        // Then - Should only return options from task_run_id 1
        $this->assertEquals(['Initialize'], $result['operation']);
        $this->assertEquals([WorkflowStatesContract::STATUS_COMPLETED], $result['status']);
    }

    public function test_fieldOptions_excludesNullOperationsAndStatuses(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Create processes with null values
        TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => null, // Null operation
            'status'      => WorkflowStatesContract::STATUS_COMPLETED,
        ]);

        TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => 'Initialize',
            'status'      => WorkflowStatesContract::STATUS_COMPLETED,
        ]);

        // When
        $result = $this->repository->fieldOptions();

        // Then - Should exclude null operation
        $this->assertEquals(['Initialize'], $result['operation']);
        $this->assertNotContains(null, $result['operation']);
    }
}
