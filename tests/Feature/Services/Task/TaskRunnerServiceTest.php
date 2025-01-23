<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\TaskDefinition;
use App\Services\Task\TaskRunnerService;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class TaskRunnerServiceTest extends AuthenticatedTestCase
{
    use AiMockData;

    public function test_prepareTaskRun_createsTaskRunWithSingleProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(1, $taskRun->taskProcesses, 'TaskRun should have a single TaskProcess');
        $this->assertEquals(1, $taskRun->process_count, 'TaskRun should have a process count of 1');
    }

    public function test_continue_whenTaskRunIsPending_dispatchesAJobForTaskProcessToCompletion(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskProcess    = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then After
        $taskRun->refresh();
        $taskProcess->refresh();
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed');
        $this->assertFalse($taskRun->isFailed(), 'TaskRun should not fail');
        $this->assertFalse($taskRun->isStopped(), 'TaskRun should not be stopped');

        $this->assertTrue($taskProcess->isCompleted(), 'TaskProcess should be completed');
        $this->assertFalse($taskProcess->isFailed(), 'TaskProcess should not fail');
        $this->assertFalse($taskProcess->isStopped(), 'TaskProcess should not be stopped');

        $this->assertEquals(1, $taskProcess->jobDispatches()->count(), 'TaskProcess should have a single JobDispatch');
    }

    public function test_continue_whenTaskRunIsStopped_doesNotDispatchAJobForTaskProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskRun->update(['stopped_at' => now()]);
        $taskProcess = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then
        $this->assertEquals(0, $taskProcess->jobDispatches()->count(), 'TaskProcess should not have been dispatched');
    }

    public function test_continue_whenTaskRunIsFailed_doesNotDispatchAJobForTaskProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskRun->update(['failed_at' => now()]);
        $taskProcess = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then
        $this->assertEquals(0, $taskProcess->jobDispatches()->count(), 'TaskProcess should not have been dispatched');
    }
}
