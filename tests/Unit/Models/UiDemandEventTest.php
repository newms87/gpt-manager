<?php

namespace Tests\Unit\Models;

use App\Events\UiDemandUpdatedEvent;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_ui_demand_booted_method_exists_and_can_be_called()
    {
        // Given/When - Test that the booted method exists and doesn't throw errors
        UiDemand::booted();

        // Then - No exception thrown
        $this->assertTrue(true);
    }

    public function test_ui_demand_tracks_changes_correctly()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
        ]);

        // When
        $uiDemand->status = UiDemand::STATUS_COMPLETED;
        $uiDemand->save();

        // Then - Model should track that status was changed
        $this->assertTrue($uiDemand->wasChanged('status'));
    }

    public function test_ui_demand_workflow_run_relationship_works()
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        
        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);
        
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'workflow_run_id' => $workflowRun->id,
        ]);

        // When
        $uiDemand->update(['status' => UiDemand::STATUS_COMPLETED]);

        // Then
        $this->assertEquals($workflowRun->id, $uiDemand->workflow_run_id);
        $this->assertEquals(UiDemand::STATUS_COMPLETED, $uiDemand->status);
    }

    public function test_ui_demand_event_contains_correct_data()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
        ]);

        // When
        $event = new UiDemandUpdatedEvent($uiDemand, 'updated');

        // Then
        $this->assertEquals($uiDemand->id, $event->getUiDemand()->id);
        $this->assertEquals('private-UiDemand.' . $uiDemand->team_id, $event->broadcastOn()->name);
        
        $data = $event->data();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals($uiDemand->id, $data['id']);
    }

    public function test_ui_demand_event_broadcast_channel_is_team_scoped()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // When
        $event = new UiDemandUpdatedEvent($uiDemand, 'updated');

        // Then
        $channelName = $event->broadcastOn()->name;
        $this->assertStringContainsString('UiDemand.' . $uiDemand->team_id, $channelName);
        $this->assertStringContainsString('private-', $channelName);
    }

    public function test_ui_demand_event_data_includes_workflow_state_helpers()
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status' => UiDemand::STATUS_DRAFT,
        ]);

        // When
        $event = new UiDemandUpdatedEvent($uiDemand, 'updated');
        $data = $event->data();

        // Then
        $this->assertArrayHasKey('can_extract_data', $data);
        $this->assertArrayHasKey('can_write_demand', $data);
        $this->assertArrayHasKey('is_extract_data_running', $data);
        $this->assertArrayHasKey('is_write_demand_running', $data);
    }
}