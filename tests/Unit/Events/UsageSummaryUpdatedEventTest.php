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

    public function test_usage_summary_updated_event_is_broadcast_on_save(): void
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $eventFired    = false;
        $capturedEvent = null;

        Event::listen(UsageSummaryUpdatedEvent::class, function ($event) use (&$eventFired, &$capturedEvent) {
            $eventFired    = true;
            $capturedEvent = $event;
        });

        $usageSummary = UsageSummary::create([
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

        $this->assertTrue($eventFired, 'UsageSummaryUpdatedEvent should have been fired');
        $this->assertNotNull($capturedEvent);
        $this->assertEquals($usageSummary->id, $capturedEvent->getUsageSummary()->id);
    }

    public function test_usage_summary_event_has_correct_channel(): void
    {
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

        $event   = new UsageSummaryUpdatedEvent($usageSummary, 'updated');
        $channel = $event->broadcastOn();

        $this->assertEquals('private-UsageSummary.' . $this->user->currentTeam->id, $channel->name);
    }

    public function test_usage_summary_event_has_correct_data_format(): void
    {
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

        $event = new UsageSummaryUpdatedEvent($usageSummary, 'updated');
        $data  = $event->data();

        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('input_tokens', $data);
        $this->assertArrayHasKey('output_tokens', $data);
        $this->assertArrayHasKey('total_tokens', $data);
        $this->assertArrayHasKey('total_cost', $data);
        $this->assertArrayHasKey('__type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('object_type', $data);
        $this->assertArrayHasKey('object_id', $data);

        $this->assertEquals('UsageSummaryResource', $data['__type']);
        $this->assertEquals($usageSummary->id, $data['id']);
        $this->assertEquals(UiDemand::class, $data['object_type']);
        $this->assertEquals(150, $data['total_tokens']);
    }
}
