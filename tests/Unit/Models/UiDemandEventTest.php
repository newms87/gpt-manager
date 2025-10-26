<?php

namespace Tests\Unit\Models;

use App\Events\UiDemandUpdatedEvent;
use App\Models\Demand\UiDemand;
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

    public function test_uiDemand_updated_firesEvent(): void
    {
        // Given
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'status'  => UiDemand::STATUS_DRAFT,
        ]);

        $eventFired = false;

        Event::listen(UiDemandUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $uiDemand->update(['status' => UiDemand::STATUS_COMPLETED]);

        // Then
        $this->assertTrue($eventFired, 'UiDemandUpdatedEvent should fire when UiDemand is updated');
    }
}
