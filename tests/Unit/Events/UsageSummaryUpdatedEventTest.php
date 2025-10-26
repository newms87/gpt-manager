<?php

namespace Tests\Unit\Events;

use App\Events\UsageSummaryUpdatedEvent;
use App\Models\Demand\UiDemand;
use App\Models\Usage\UsageSummary;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UsageSummaryUpdatedEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_usageSummary_created_firesEvent(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $eventFired = false;

        Event::listen(UsageSummaryUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        UsageSummary::create([
            'object_type'   => UiDemand::class,
            'object_id'     => $uiDemand->id,
            'object_id_int' => $uiDemand->id,
            'count'         => 1,
            'run_time_ms'   => 1000,
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'total_cost'    => 0.003,
            'request_count' => 1,
            'data_volume'   => 0,
        ]);

        // Then
        $this->assertTrue($eventFired, 'UsageSummaryUpdatedEvent should fire when UsageSummary is created');
    }

    public function test_teamId_resolvedViaPolymorphicRelationship(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $usageSummary = UsageSummary::create([
            'object_type'   => UiDemand::class,
            'object_id'     => $uiDemand->id,
            'object_id_int' => $uiDemand->id,
            'count'         => 1,
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'total_cost'    => 0.003,
            'request_count' => 1,
        ]);

        // Subscribe to ensure broadcastOn() returns channels
        $this->postJson('/api/pusher/subscribe', [
            'resource_type'      => 'UsageSummary',
            'model_id_or_filter' => true,
        ]);

        // When
        $event    = new UsageSummaryUpdatedEvent($usageSummary, 'created');
        $channels = $event->broadcastOn();

        // Then - Should resolve team_id from polymorphic object relationship
        $this->assertNotEmpty($channels, 'Should broadcast when team_id is resolved');
        $this->assertEquals('private-UsageSummary.' . $this->user->currentTeam->id, $channels[0]->name);
    }
}
