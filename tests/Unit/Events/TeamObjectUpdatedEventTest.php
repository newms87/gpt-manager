<?php

namespace Tests\Unit\Events;

use App\Events\TeamObjectUpdatedEvent;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TeamObjectUpdatedEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_teamObject_created_firesEvent(): void
    {
        // Given
        $eventFired = false;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired) {
            $eventFired = true;
        });

        // When
        TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'TestObject',
            'name'    => 'Test Object Name',
        ]);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should fire when TeamObject is created');
    }

    public function test_teamObject_updated_firesEvent(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'TestObject',
            'name'    => 'Original Name',
        ]);

        $eventFired = false;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $teamObject->update(['name' => 'Updated Name']);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should fire when TeamObject is updated');
    }

    public function test_teamObjectAttribute_saved_firesParentTeamObjectEvent(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'TestObject',
            'name'    => 'Test Parent Object',
        ]);

        $eventFired = false;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired) {
            $eventFired = true;
        });

        // When
        TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name'           => 'test_attribute',
            'text_value'     => 'test_value',
        ]);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should fire when TeamObjectAttribute is saved');
    }

    public function test_teamObjectRelationship_saved_firesParentTeamObjectEvent(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'TestObject',
            'name'    => 'Test Parent Object',
        ]);

        $relatedObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'RelatedObject',
            'name'    => 'Test Related Object',
        ]);

        $eventFired = false;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired) {
            $eventFired = true;
        });

        // When
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $teamObject->id,
            'related_team_object_id' => $relatedObject->id,
            'relationship_name'      => 'related_to',
        ]);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should fire when TeamObjectRelationship is saved');
    }
}
