<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\TaskDefinition;
use App\Services\Task\TaskRunner;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class TaskRunnerTest extends AuthenticatedTestCase
{
    use AiMockData;

    public function test_prepareTaskRun_createsTaskRunWithSingleProcess(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();

        // When
        $taskRun = TaskRunner::makeRunnerForDefinition($taskDefinition)->prepareTaskRun();

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(1, $taskRun->taskProcesses, 'TaskRun should have a single TaskProcess');
        $this->assertEquals(1, $taskRun->process_count, 'TaskRun should have a process count of 1');
    }
}
