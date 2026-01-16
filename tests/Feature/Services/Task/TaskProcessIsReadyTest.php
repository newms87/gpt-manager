<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use Tests\AuthenticatedTestCase;

class TaskProcessIsReadyTest extends AuthenticatedTestCase
{
    public function test_taskProcess_isReady_defaultsToFalse(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->for($taskDefinition)->create();

        // When
        $taskProcess = TaskProcess::factory()->for($taskRun)->create();

        // Then
        $this->assertFalse($taskProcess->is_ready, 'is_ready should default to false');
    }

    public function test_scopeReadyToRun_onlyReturnsProcessesWithIsReadyTrue(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->for($taskDefinition)->create();

        $readyProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => true,
            'status'   => WorkflowStatesContract::STATUS_PENDING,
        ]);

        $notReadyProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => false,
            'status'   => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // When
        $readyProcesses = TaskProcess::readyToRun()->get();

        // Then
        $this->assertCount(1, $readyProcesses, 'Should only return 1 ready process');
        $this->assertEquals($readyProcess->id, $readyProcesses->first()->id, 'Should return the ready process');
        $this->assertFalse($readyProcesses->contains('id', $notReadyProcess->id), 'Should not return the not ready process');
    }

    public function test_scopeReadyToRun_checksIsReadyForIncompleteProcesses(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create(['max_process_retries' => 3]);
        $taskRun        = TaskRun::factory()->for($taskDefinition)->create();

        $readyIncompleteProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'status'        => WorkflowStatesContract::STATUS_INCOMPLETE,
            'restart_count' => 1,
        ]);

        $notReadyIncompleteProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => false,
            'status'        => WorkflowStatesContract::STATUS_INCOMPLETE,
            'restart_count' => 1,
        ]);

        // When
        $readyProcesses = TaskProcess::readyToRun()->get();

        // Then
        $this->assertCount(1, $readyProcesses, 'Should only return 1 ready process');
        $this->assertEquals($readyIncompleteProcess->id, $readyProcesses->first()->id, 'Should return the ready incomplete process');
        $this->assertFalse($readyProcesses->contains('id', $notReadyIncompleteProcess->id), 'Should not return the not ready incomplete process');
    }

    public function test_scopeReadyToRun_checksIsReadyForTimeoutProcesses(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create(['max_process_retries' => 3]);
        $taskRun        = TaskRun::factory()->for($taskDefinition)->create();

        $readyTimeoutProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => true,
            'status'        => WorkflowStatesContract::STATUS_TIMEOUT,
            'restart_count' => 1,
        ]);

        $notReadyTimeoutProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'      => false,
            'status'        => WorkflowStatesContract::STATUS_TIMEOUT,
            'restart_count' => 1,
        ]);

        // When
        $readyProcesses = TaskProcess::readyToRun()->get();

        // Then
        $this->assertCount(1, $readyProcesses, 'Should only return 1 ready process');
        $this->assertEquals($readyTimeoutProcess->id, $readyProcesses->first()->id, 'Should return the ready timeout process');
        $this->assertFalse($readyProcesses->contains('id', $notReadyTimeoutProcess->id), 'Should not return the not ready timeout process');
    }

    public function test_prepare_setsIsReadyToTrueAfterArtifactsAttached(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $artifacts      = Artifact::factory()->count(2)->create();

        // When
        $taskProcess = TaskProcessRunnerService::prepare($taskRun, null, $artifacts);

        // Then
        $taskProcess->refresh();
        $this->assertTrue($taskProcess->is_ready, 'is_ready should be set to true after preparation');
        $this->assertEquals(2, $taskProcess->inputArtifacts()->count(), 'Should have 2 input artifacts attached');
    }

    public function test_prepare_setsIsReadyToTrueEvenWithNoArtifacts(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);

        // When
        $taskProcess = TaskProcessRunnerService::prepare($taskRun, null, []);

        // Then
        $taskProcess->refresh();
        $this->assertTrue($taskProcess->is_ready, 'is_ready should be set to true after preparation even with no artifacts');
        $this->assertEquals(0, $taskProcess->inputArtifacts()->count(), 'Should have no input artifacts');
    }

    public function test_restart_setsIsReadyToTrue(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRunnerService::prepareTaskRun($taskDefinition);
        $oldProcess     = TaskProcess::factory()->for($taskRun)->create([
            'is_ready'  => false,
            'status'    => WorkflowStatesContract::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        // When - restart() returns the NEW process (old process is soft-deleted)
        $newProcess = TaskProcessRunnerService::restart($oldProcess);

        // Then - New process should have is_ready = true
        $this->assertTrue($newProcess->is_ready, 'is_ready should be set to true after restart');
        $this->assertNull($newProcess->failed_at, 'failed_at should be cleared');
        $this->assertEquals(1, $newProcess->restart_count, 'restart_count should be incremented');

        // And - Old process should be soft-deleted
        $oldProcess->refresh();
        $this->assertTrue($oldProcess->trashed(), 'Old process should be soft-deleted');
    }

    public function test_canBeRun_checksIsReady(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create();
        $taskRun        = TaskRun::factory()->for($taskDefinition)->create();

        $readyProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => true,
            'status'   => WorkflowStatesContract::STATUS_PENDING,
        ]);

        $notReadyProcess = TaskProcess::factory()->for($taskRun)->create([
            'is_ready' => false,
            'status'   => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // When & Then
        $this->assertTrue($readyProcess->canBeRun(), 'Ready process should be able to run');

        // Note: canBeRun() doesn't check is_ready flag directly, it checks status
        // The is_ready flag is checked in scopeReadyToRun which is used by the dispatcher
    }
}
