<?php

namespace Tests\Feature\Events;

use App\Events\TaskProcessUpdatedEvent;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskProcessUpdatedEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_taskProcess_statusChange_firesEvent(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'workflow_run_id' => $workflowRun->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
        ]);

        $eventFired = false;

        Event::listen(TaskProcessUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When - Update a watched field
        $taskProcess->update(['activity' => 'Processing data']);

        // Then
        $this->assertTrue($eventFired, 'TaskProcessUpdatedEvent should fire when watched fields change');
    }


    public function test_teamId_resolvedViaTaskRunRelationship(): void
    {
        // Given
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
            'workflow_run_id' => $workflowRun->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
        ]);

        // Subscribe to ensure broadcastOn() returns channels
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'TaskProcess',
            'model_id_or_filter' => true,
        ]);

        // When
        $event = new TaskProcessUpdatedEvent($taskProcess, 'updated');
        $channels = $event->broadcastOn();

        // Then - Should resolve team_id from TaskRun->TaskDefinition
        $this->assertNotEmpty($channels, 'Should broadcast when team_id is resolved');
        $this->assertEquals('private-TaskProcess.' . $this->user->currentTeam->id, $channels[0]->name);
    }
}
