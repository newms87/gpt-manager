<?php

namespace Tests\Unit\Events;

use App\Models\TeamObject\TeamObject;
use App\Resources\TeamObject\TeamObjectResource;
use Newms87\Danx\Events\ModelSavedEvent;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ModelSavedEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_createdEvent_usesCreatedData(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object',
        ]);

        // When - Create event with 'created' type
        $event = new class($teamObject, 'created', TeamObjectResource::class, $teamObject->team_id) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*'    => false,
                    'type' => true,
                    'name' => true,
                ]);
            }

            protected function updatedData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*'           => false,
                    'updated_at'  => true,
                ]);
            }
        };

        $data = $event->data();

        // Then - Should have fields from createdData() + auto fields
        $this->assertArrayHasKey('id', $data); // Auto field
        $this->assertArrayHasKey('__type', $data); // Auto field
        $this->assertArrayHasKey('type', $data); // From createdData
        $this->assertArrayHasKey('name', $data); // From createdData

        // Should NOT have updatedData fields
        $this->assertArrayNotHasKey('updated_at', $data);
    }

    public function test_updatedEvent_usesUpdatedData(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object',
        ]);

        // When - Create event with 'updated' type
        $event = new class($teamObject, 'updated', TeamObjectResource::class, $teamObject->team_id) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*'    => false,
                    'type' => true,
                    'name' => true,
                ]);
            }

            protected function updatedData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*'          => false,
                    'updated_at' => true,
                ]);
            }
        };

        $data = $event->data();

        // Then - Should have fields from updatedData() + auto fields
        $this->assertArrayHasKey('id', $data); // Auto field
        $this->assertArrayHasKey('__type', $data); // Auto field
        $this->assertArrayHasKey('updated_at', $data); // From updatedData

        // Should NOT have createdData-only fields
        $this->assertArrayNotHasKey('type', $data);
        $this->assertArrayNotHasKey('name', $data);
    }

    public function test_wildcardFalse_excludesNonSpecifiedFields(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type' => 'TestObject',
            'name' => 'Test Object',
            'description' => 'Test Description',
        ]);

        // When - Use '*' => false to exclude all except specified
        $event = new class($teamObject, 'updated', TeamObjectResource::class, $teamObject->team_id) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*'    => false,
                    'name' => true,
                ]);
            }

            protected function updatedData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*'    => false,
                    'name' => true,
                ]);
            }
        };

        $data = $event->data();

        // Then - Should only have specified field + auto fields
        $this->assertArrayHasKey('name', $data);

        // Should NOT have other resource fields
        $this->assertArrayNotHasKey('type', $data);
        $this->assertArrayNotHasKey('description', $data);
        $this->assertArrayNotHasKey('url', $data);
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('attributes', $data);
        $this->assertArrayNotHasKey('relations', $data);
    }

    public function test_autoFields_alwaysIncluded(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // When - Even with minimal field selection
        $event = new class($teamObject, 'updated', TeamObjectResource::class, $teamObject->team_id) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*' => false,
                ]);
            }

            protected function updatedData(): array
            {
                return TeamObjectResource::make($this->model, [
                    '*' => false,
                ]);
            }
        };

        $data = $event->data();

        // Then - Auto fields should always be present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('__type', $data);
        $this->assertArrayHasKey('__timestamp', $data);
        $this->assertArrayHasKey('__deleted_at', $data);

        $this->assertEquals($teamObject->id, $data['id']);
        $this->assertEquals('TeamObjectResource', $data['__type']);
    }

    public function test_broadcastOn_withSubscription_returnsChannels(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Subscribe to TeamObject events
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'TeamObject',
            'model_id_or_filter' => true,
        ]);

        // When
        $event = new class($teamObject, 'updated', TeamObjectResource::class, $teamObject->team_id) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return [];
            }

            protected function updatedData(): array
            {
                return [];
            }
        };

        $channels = $event->broadcastOn();

        // Then - Should return team channel
        $this->assertIsArray($channels);
        $this->assertCount(1, $channels);
        $this->assertEquals('private-TeamObject.' . $this->user->currentTeam->id, $channels[0]->name);
    }

    public function test_broadcastOn_withoutSubscription_returnsEmptyArray(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // No subscription created

        // When
        $event = new class($teamObject, 'updated', TeamObjectResource::class, $teamObject->team_id) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return [];
            }

            protected function updatedData(): array
            {
                return [];
            }
        };

        $channels = $event->broadcastOn();

        // Then - Should return empty array (no broadcast)
        $this->assertIsArray($channels);
        $this->assertEmpty($channels);
    }

    public function test_broadcastOn_withoutTeamId_returnsEmptyArray(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Subscribe to TeamObject events
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'TeamObject',
            'model_id_or_filter' => true,
        ]);

        // When - Create event with null team_id
        $event = new class($teamObject, 'updated', TeamObjectResource::class, null) extends ModelSavedEvent {
            protected function createdData(): array
            {
                return [];
            }

            protected function updatedData(): array
            {
                return [];
            }
        };

        $channels = $event->broadcastOn();

        // Then - Should return empty array (no team = no broadcast)
        $this->assertIsArray($channels);
        $this->assertEmpty($channels);
    }
}
