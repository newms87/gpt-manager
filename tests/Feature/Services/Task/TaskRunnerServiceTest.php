<?php

namespace Tests\Feature\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
use App\Models\Workflow\Artifact;
use App\Services\Task\TaskRunnerService;
use Tests\AuthenticatedTestCase;

class TaskRunnerServiceTest extends AuthenticatedTestCase
{
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

    public function test_prepareTaskRun_createsTaskRunWithOneProcessForEachAgent(): void
    {
        // Given
        $agentA         = Agent::factory()->create();
        $agentB         = Agent::factory()->create();
        $taskDefinition = TaskDefinition::factory()
            ->withDefinitionAgent(['agent_id' => $agentA])
            ->withDefinitionAgent(['agent_id' => $agentB])
            ->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(2, $taskRun->taskProcesses, 'TaskRun should have 2 processes: 1 for each agent');
        $this->assertEquals(2, $taskRun->process_count, 'The process_count should reflect the 2 processes created');

        $processes = $taskRun->taskProcesses;
        $processA  = $processes->filter(fn($process) => $process->taskDefinitionAgent->agent_id === $agentA->id)->first();
        $processB  = $processes->filter(fn($process) => $process->taskDefinitionAgent->agent_id === $agentB->id)->first();

        $this->assertNotNull($processA, 'Process A should have been found matching agent A');
        $this->assertNotNull($processB, 'Process B should have been found matching agent B');
    }

    public function test_prepareTaskRun_withArtifacts_createsOneProcessForAllArtifacts(): void
    {
        // Given
        $agentA         = Agent::factory()->create();
        $taskDefinition = TaskDefinition::factory()->withDefinitionAgent(['agent_id' => $agentA])->create();
        $artifacts      = Artifact::factory()->count(2)->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition, $artifacts);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(1, $taskRun->taskProcesses, 'TaskRun should have a single process for all artifacts');

        $process = $taskRun->taskProcesses->first();

        $this->assertEquals(2, $process->inputArtifacts()->count(), 'Process should have 2 input artifacts');
    }

    public function test_prepareTaskRun_withArtifactsAndMultipleAgents_createsAProcessWithAllArtifactsForEachAgent(): void
    {
        // Given
        $agentA         = Agent::factory()->create();
        $agentB         = Agent::factory()->create();
        $taskDefinition = TaskDefinition::factory()
            ->withDefinitionAgent(['agent_id' => $agentA])
            ->withDefinitionAgent(['agent_id' => $agentB])
            ->create();
        $artifacts      = Artifact::factory()->count(2)->create();

        // When
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition, $artifacts);

        // Then
        $taskRun->refresh();
        $this->assertNotNull($taskRun, 'TaskRun should be created');
        $this->assertCount(2, $taskRun->taskProcesses, 'TaskRun should have 2 processes: 1 for each agent');

        $processes = $taskRun->taskProcesses;
        $processA  = $processes->filter(fn($process) => $process->taskDefinitionAgent->agent_id === $agentA->id)->first();
        $processB  = $processes->filter(fn($process) => $process->taskDefinitionAgent->agent_id === $agentB->id)->first();

        $this->assertEquals(2, $processA->inputArtifacts()->count(), 'Process A should have 2 input artifacts');
        $this->assertEquals(2, $processB->inputArtifacts()->count(), 'Process B should have 2 input artifacts');
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

    public function test_continue_whenRunningAgentThread_completedTaskHasOutputArtifact(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->withDefinitionAgent()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $taskProcess    = $taskRun->taskProcesses->first();

        // When
        TaskRunnerService::continue($taskRun);

        // Then After
        $taskRun->refresh();
        $taskProcess->refresh();
        $this->assertTrue($taskRun->isCompleted(), 'TaskRun should be completed');
        $this->assertEquals(1, $taskProcess->outputArtifacts()->count(), 'TaskProcess should have 1 output artifact');
    }
}
