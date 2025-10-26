<?php

namespace Tests\Feature\Events;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowRunUpdatedEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_workflowRun_updated_firesEvent(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $eventFired = false;

        Event::listen(WorkflowRunUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $workflowRun->update(['name' => 'Updated Name']);

        // Then
        $this->assertTrue($eventFired, 'WorkflowRunUpdatedEvent should fire when WorkflowRun is updated');
    }
}
