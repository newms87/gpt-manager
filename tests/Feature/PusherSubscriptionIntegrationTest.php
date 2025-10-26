<?php

namespace Tests\Feature;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class PusherSubscriptionIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    #[Test]
    public function full_workflow_subscribe_event_broadcast_unsubscribe(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Step 1: Subscribe
        $subscribeResponse = $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
        ]);

        $subscribeResponse->assertStatus(200);

        // Verify subscription in cache
        $cacheKey = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:id:{$workflowRun->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Step 2: Trigger event
        $eventFired = false;
        Event::listen(WorkflowRunUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels = $event->broadcastOn();

        // Verify event broadcast
        $this->assertNotEmpty($channels);
        $this->assertEquals("private-WorkflowRun.{$this->user->currentTeam->id}", $channels[0]->name);

        // Step 3: Unsubscribe
        $unsubscribeResponse = $this->postJson('/api/pusher/unsubscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
        ]);

        $unsubscribeResponse->assertStatus(200);

        // Verify subscription removed from cache
        $this->assertFalse(Cache::has($cacheKey));

        // Step 4: Verify no longer receiving events
        $event2 = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels2 = $event2->broadcastOn();

        $this->assertEmpty($channels2);
    }

    #[Test]
    public function keepalive_extends_subscription_ttl(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Subscribe
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
        ]);

        $cacheKey = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:id:{$workflowRun->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // When - Send keepalive
        $keepaliveResponse = $this->postJson('/api/pusher/keepalive', [
            'subscriptions' => [
                [
                    'resource_type' => 'WorkflowRun',
                    'model_id_or_filter' => $workflowRun->id,
                ],
            ],
        ]);

        // Then
        $keepaliveResponse->assertStatus(200);
        $this->assertTrue(Cache::has($cacheKey));

        // Verify event still broadcast
        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels = $event->broadcastOn();
        $this->assertNotEmpty($channels);
    }

    #[Test]
    public function multiple_users_receive_same_event(): void
    {
        // Given
        $user2 = \App\Models\User::factory()->create();
        // Add user2 to the same team as user1
        $this->user->currentTeam->users()->attach($user2->id);
        $user2->currentTeam = $this->user->currentTeam;
        $user2->save();

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Both users subscribe
        $this->actingAs($this->user)
            ->postJson('/api/pusher/subscribe', [
                'resource_type' => 'WorkflowRun',
                'model_id_or_filter' => $workflowRun->id,
            ]);

        $this->actingAs($user2)
            ->postJson('/api/pusher/subscribe', [
                'resource_type' => 'WorkflowRun',
                'model_id_or_filter' => $workflowRun->id,
            ]);

        // When - Event is triggered
        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels = $event->broadcastOn();

        // Then - Both users should receive on team channel
        $this->assertNotEmpty($channels);
        $this->assertCount(1, $channels, 'Should broadcast to single team channel');
        $this->assertEquals("private-WorkflowRun.{$this->user->currentTeam->id}", $channels[0]->name);

        // Verify both users are in cache
        $cacheKey = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:id:{$workflowRun->id}";
        $subscribers = Cache::get($cacheKey);
        $this->assertCount(2, $subscribers);
        $this->assertContains($this->user->id, $subscribers);
        $this->assertContains($user2->id, $subscribers);
    }

    #[Test]
    public function filter_based_subscription_end_to_end(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $runningWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at' => now(),
        ]);

        $completedWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        // Subscribe with filter
        $filter = ['status' => 'Running'];

        $subscribeResponse = $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
        ]);

        $subscribeResponse->assertStatus(200);

        // When - Event for running workflow
        $event1 = new WorkflowRunUpdatedEvent($runningWorkflowRun, 'updated');
        $channels1 = $event1->broadcastOn();

        // Then - Should broadcast
        $this->assertNotEmpty($channels1);

        // When - Event for completed workflow
        $event2 = new WorkflowRunUpdatedEvent($completedWorkflowRun, 'updated');
        $channels2 = $event2->broadcastOn();

        // Then - Should NOT broadcast
        $this->assertEmpty($channels2);

        // Unsubscribe
        $unsubscribeResponse = $this->postJson('/api/pusher/unsubscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
        ]);

        $unsubscribeResponse->assertStatus(200);

        // Verify no longer receives events
        $event3 = new WorkflowRunUpdatedEvent($runningWorkflowRun, 'updated');
        $channels3 = $event3->broadcastOn();
        $this->assertEmpty($channels3);
    }

    #[Test]
    public function simultaneous_channel_wide_and_model_specific_subscriptions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Subscribe to both channel-wide and model-specific
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => true,
        ]);

        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
        ]);

        // When - Event is triggered
        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels = $event->broadcastOn();

        // Then - Should broadcast (deduplicated to single team channel)
        $this->assertNotEmpty($channels);
        $this->assertCount(1, $channels);
        $this->assertEquals("private-WorkflowRun.{$this->user->currentTeam->id}", $channels[0]->name);

        // Unsubscribe from channel-wide
        $this->postJson('/api/pusher/unsubscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => true,
        ]);

        // Should still broadcast (model-specific subscription still active)
        $event2 = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels2 = $event2->broadcastOn();
        $this->assertNotEmpty($channels2);

        // Unsubscribe from model-specific
        $this->postJson('/api/pusher/unsubscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
        ]);

        // Should NOT broadcast (no subscriptions remain)
        $event3 = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels3 = $event3->broadcastOn();
        $this->assertEmpty($channels3);
    }

    #[Test]
    public function keepalive_refreshes_multiple_subscriptions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Subscribe to multiple resources
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun1->id,
        ]);

        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun2->id,
        ]);

        // When - Keepalive for both subscriptions
        $keepaliveResponse = $this->postJson('/api/pusher/keepalive', [
            'subscriptions' => [
                [
                    'resource_type' => 'WorkflowRun',
                    'model_id_or_filter' => $workflowRun1->id,
                ],
                [
                    'resource_type' => 'WorkflowRun',
                    'model_id_or_filter' => $workflowRun2->id,
                ],
            ],
        ]);

        // Then
        $keepaliveResponse->assertStatus(200);

        // Verify both subscriptions still active
        $cacheKey1 = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:id:{$workflowRun1->id}";
        $cacheKey2 = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:id:{$workflowRun2->id}";

        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));
    }

    #[Test]
    public function keepalive_with_filter_based_subscription(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at' => now(),
        ]);

        $filter = ['status' => 'Running'];

        // Subscribe with filter
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
        ]);

        // When - Keepalive
        $keepaliveResponse = $this->postJson('/api/pusher/keepalive', [
            'subscriptions' => [
                [
                    'resource_type' => 'WorkflowRun',
                    'model_id_or_filter' => ['filter' => $filter],
                ],
            ],
        ]);

        // Then
        $keepaliveResponse->assertStatus(200);

        // Verify subscription still active
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($definitionKey));

        // Verify still receives events
        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $channels = $event->broadcastOn();
        $this->assertNotEmpty($channels);
    }

    #[Test]
    public function unsubscribe_from_one_subscription_does_not_affect_others(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Subscribe to multiple resources
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun1->id,
        ]);

        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun2->id,
        ]);

        // When - Unsubscribe from workflowRun1
        $this->postJson('/api/pusher/unsubscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun1->id,
        ]);

        // Then - workflowRun1 subscription should be removed
        $event1 = new WorkflowRunUpdatedEvent($workflowRun1, 'updated');
        $channels1 = $event1->broadcastOn();
        $this->assertEmpty($channels1);

        // workflowRun2 subscription should still be active
        $event2 = new WorkflowRunUpdatedEvent($workflowRun2, 'updated');
        $channels2 = $event2->broadcastOn();
        $this->assertNotEmpty($channels2);
    }

    #[Test]
    public function event_payload_matches_resource_structure(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'name' => 'Integration Test Workflow',
            'started_at' => now(),
        ]);

        // Subscribe
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
        ]);

        // When - Event is triggered
        $event = new WorkflowRunUpdatedEvent($workflowRun, 'updated');
        $data = $event->data();

        // Then - Payload should be lightweight and match expected structure
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('workflow_definition_id', $data);

        $this->assertEquals($workflowRun->id, $data['id']);
        $this->assertEquals('Integration Test Workflow', $data['name']);
        $this->assertEquals('Running', $data['status']);

        // Should NOT have relationships
        $this->assertArrayNotHasKey('workflowDefinition', $data);
        $this->assertArrayNotHasKey('taskRuns', $data);
    }
}
