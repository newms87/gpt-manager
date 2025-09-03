<?php

namespace Tests\Unit\Events;

use App\Events\TeamObjectUpdatedEvent;
use App\Models\Schema\SchemaDefinition;
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

    public function test_teamObject_saved_broadcastsEvent(): void
    {
        // Given
        $eventFired = false;
        $capturedEvent = null;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired, &$capturedEvent) {
            $eventFired = true;
            $capturedEvent = $event;
        });

        // When
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object Name'
        ]);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should have been fired when TeamObject is saved');
        $this->assertNotNull($capturedEvent);
        $this->assertEquals($teamObject->id, $capturedEvent->getTeamObject()->id);
    }

    public function test_teamObjectAttribute_saved_broadcastsParentTeamObjectLightweightEvent(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Parent Schema'
        ]);
        
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Parent Object',
            'schema_definition_id' => $schemaDefinition->id
        ]);

        $eventFired = false;
        $capturedEvent = null;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired, &$capturedEvent) {
            $eventFired = true;
            $capturedEvent = $event;
        });

        // When
        $attribute = TeamObjectAttribute::factory()->create([
            'team_object_id' => $teamObject->id,
            'name' => 'test_attribute',
            'text_value' => 'test_value'
        ]);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should have been fired when TeamObjectAttribute is saved');
        $this->assertNotNull($capturedEvent);
        $this->assertEquals($teamObject->id, $capturedEvent->getTeamObject()->id);
        
        // Verify lightweight event data structure
        $data = $capturedEvent->data();
        $this->assertEquals('TeamObjectEvent', $data['__type']);
        $this->assertEquals($teamObject->id, $data['id']);
        $this->assertEquals($schemaDefinition->id, $data['schema_definition_id']);
        $this->assertCount(5, $data, 'Cascaded event should maintain lightweight structure');
    }

    public function test_teamObjectRelationship_saved_broadcastsParentTeamObjectLightweightEvent(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Parent Schema'
        ]);
        
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Parent Object',
            'schema_definition_id' => $schemaDefinition->id
        ]);

        $relatedObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'RelatedObject',
            'name' => 'Test Related Object'
        ]);

        $eventFired = false;
        $capturedEvent = null;

        Event::listen(TeamObjectUpdatedEvent::class, function ($event) use (&$eventFired, &$capturedEvent) {
            $eventFired = true;
            $capturedEvent = $event;
        });

        // When
        $relationship = TeamObjectRelationship::factory()->create([
            'team_object_id' => $teamObject->id,
            'related_team_object_id' => $relatedObject->id,
            'relationship_name' => 'related_to'
        ]);

        // Then
        $this->assertTrue($eventFired, 'TeamObjectUpdatedEvent should have been fired when TeamObjectRelationship is saved');
        $this->assertNotNull($capturedEvent);
        $this->assertEquals($teamObject->id, $capturedEvent->getTeamObject()->id);
        
        // Verify lightweight event data structure
        $data = $capturedEvent->data();
        $this->assertEquals('TeamObjectEvent', $data['__type']);
        $this->assertEquals($teamObject->id, $data['id']);
        $this->assertEquals($schemaDefinition->id, $data['schema_definition_id']);
        $this->assertCount(5, $data, 'Cascaded event should maintain lightweight structure');
    }

    public function test_teamObjectUpdatedEvent_broadcastsToCorrectChannel(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object'
        ]);

        // When
        $event = new TeamObjectUpdatedEvent($teamObject, 'updated');
        $channel = $event->broadcastOn();

        // Then
        $this->assertEquals('private-TeamObject.' . $this->user->currentTeam->id, $channel->name);
    }

    public function test_teamObjectUpdatedEvent_hasCorrectLightweightDataFormat(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Test Schema'
        ]);
        
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object',
            'schema_definition_id' => $schemaDefinition->id,
            'root_object_id' => null // Will test fallback to id
        ]);

        // When
        $event = new TeamObjectUpdatedEvent($teamObject, 'updated');
        $data = $event->data();

        // Then - Verify lightweight structure has only required fields
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('root_object_id', $data);
        $this->assertArrayHasKey('schema_definition_id', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('__type', $data);

        // Verify the data values match
        $this->assertEquals($teamObject->id, $data['id']);
        $this->assertEquals($teamObject->id, $data['root_object_id'], 'root_object_id should fallback to id when null');
        $this->assertEquals($schemaDefinition->id, $data['schema_definition_id']);
        $this->assertNotNull($data['updated_at']);
        $this->assertEquals('TeamObjectEvent', $data['__type']);

        // Verify old resource fields are NOT included
        $this->assertArrayNotHasKey('type', $data);
        $this->assertArrayNotHasKey('name', $data);
        $this->assertArrayNotHasKey('description', $data);
        $this->assertArrayNotHasKey('url', $data);
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('attributes', $data);
        $this->assertArrayNotHasKey('relations', $data);

        // Verify only the 5 lightweight fields are present
        $this->assertCount(5, $data, 'Event data should contain exactly 5 lightweight fields');
    }

    public function test_teamObjectUpdatedEvent_withRootObjectId_usesRootObjectIdInData(): void
    {
        // Given
        $rootSchemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Root Schema'
        ]);
        
        $childSchemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Child Schema'
        ]);
        
        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'RootObject',
            'name' => 'Root Object',
            'schema_definition_id' => $rootSchemaDefinition->id
        ]);

        $childObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'ChildObject',
            'name' => 'Child Object',
            'schema_definition_id' => $childSchemaDefinition->id,
            'root_object_id' => $rootObject->id
        ]);

        // When
        $event = new TeamObjectUpdatedEvent($childObject, 'updated');
        $data = $event->data();

        // Then
        $this->assertEquals($childObject->id, $data['id']);
        $this->assertEquals($rootObject->id, $data['root_object_id'], 'Should use actual root_object_id when set');
        $this->assertEquals($childSchemaDefinition->id, $data['schema_definition_id']);
        $this->assertEquals('TeamObjectEvent', $data['__type']);
        $this->assertNotNull($data['updated_at']);
    }

    public function test_teamObjectAttribute_withoutParent_doesNotFireEvent(): void
    {
        // Given
        $eventFired = false;

        Event::listen(TeamObjectUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When - Create attribute without parent (this would be an edge case/invalid state)
        $attribute = new TeamObjectAttribute([
            'team_object_id' => null,
            'name' => 'orphaned_attribute',
            'text_value' => 'orphaned_value'
        ]);
        $attribute->save();

        // Then
        $this->assertFalse($eventFired, 'TeamObjectUpdatedEvent should not fire when attribute has no parent');
    }

    public function test_teamObjectRelationship_withoutParent_doesNotFireEvent(): void
    {
        // Given
        $eventFired = false;

        Event::listen(TeamObjectUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When - Create relationship without parent (this would be an edge case/invalid state)
        $relationship = new TeamObjectRelationship([
            'team_object_id' => null,
            'related_team_object_id' => 999, // Non-existent ID
            'relationship_name' => 'orphaned_relation'
        ]);
        $relationship->save();

        // Then
        $this->assertFalse($eventFired, 'TeamObjectUpdatedEvent should not fire when relationship has no parent');
    }

    public function test_teamObjectUpdatedEvent_withDifferentEventTypes_maintainsConsistentLightweightBehavior(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Test Schema'
        ]);
        
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object',
            'schema_definition_id' => $schemaDefinition->id
        ]);

        $eventTypes = ['created', 'updated', 'saved'];

        foreach ($eventTypes as $eventType) {
            // When
            $event = new TeamObjectUpdatedEvent($teamObject, $eventType);
            $channel = $event->broadcastOn();
            $data = $event->data();

            // Then - Each event type should have consistent channel and lightweight data structure
            $this->assertEquals('private-TeamObject.' . $this->user->currentTeam->id, $channel->name, "Channel should be consistent for event type: $eventType");
            $this->assertArrayHasKey('__type', $data, "Data should have __type for event type: $eventType");
            $this->assertEquals('TeamObjectEvent', $data['__type'], "Data __type should be TeamObjectEvent for event type: $eventType");
            $this->assertEquals($teamObject->id, $data['id'], "Data ID should match TeamObject ID for event type: $eventType");
            $this->assertCount(5, $data, "Data should contain exactly 5 lightweight fields for event type: $eventType");
        }
    }
}