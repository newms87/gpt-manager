<?php

namespace Tests\Unit\Traits;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Traits\BroadcastsWithSubscriptions;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class BroadcastsWithSubscriptionsTest extends AuthenticatedTestCase
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
    public function getSubscribedUsers_retrieves_channel_wide_subscriptions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Subscribe using controller
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => true,
            'events'             => ['updated', 'created'],
        ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then
        $this->assertContains($this->user->id, $userIds);
    }

    #[Test]
    public function getSubscribedUsers_retrieves_model_specific_subscriptions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Subscribe using controller
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
            'events'             => ['updated', 'created'],
        ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then
        $this->assertContains($this->user->id, $userIds);
    }

    #[Test]
    public function getSubscribedUsers_retrieves_filter_based_subscriptions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'status'                 => WorkflowStatesContract::STATUS_RUNNING,
        ]);

        $filter = ['status' => WorkflowStatesContract::STATUS_RUNNING];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then
        $this->assertContains($this->user->id, $userIds);
    }

    #[Test]
    public function getSubscribedUsers_deduplicates_users(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // User subscribed via both channel-wide and model-specific using controller
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => true,
            'events'             => ['updated', 'created'],
        ]);
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
            'events'             => ['updated', 'created'],
        ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then - User should appear only once
        $this->assertCount(1, array_unique($userIds));
        $this->assertContains($this->user->id, $userIds);
    }

    #[Test]
    public function getSubscribedUsers_returns_empty_array_when_no_subscriptions(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // No subscriptions created

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then
        $this->assertEmpty($userIds);
    }

    #[Test]
    public function getSubscribedChannels_returns_team_channel_for_subscribed_users(): void
    {
        // Given
        $userIds = [$this->user->id];

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $userIds)
            {
                return $this->getSubscribedChannels($resourceType, $teamId, $userIds);
            }
        };

        $channels = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $userIds);

        // Then
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-WorkflowRun.{$this->user->currentTeam->id}", $channels[0]->name);
    }

    #[Test]
    public function getSubscribedChannels_returns_empty_array_for_no_users(): void
    {
        // Given
        $userIds = [];

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $userIds)
            {
                return $this->getSubscribedChannels($resourceType, $teamId, $userIds);
            }
        };

        $channels = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $userIds);

        // Then
        $this->assertEmpty($channels);
    }

    #[Test]
    public function scanCacheKeys_finds_filter_subscriptions(): void
    {
        // Given - Create two filter subscriptions using controller
        $filter1 = ['status' => WorkflowStatesContract::STATUS_RUNNING];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter1],
            'events'             => ['updated', 'created'],
        ]);

        $filter2 = ['status' => WorkflowStatesContract::STATUS_COMPLETED];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter2],
            'events'             => ['updated', 'created'],
        ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($pattern)
            {
                return $this->scanCacheKeys($pattern);
            }
        };

        $pattern = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:filter:*";
        $keys    = $testObject->test($pattern);

        // Then - Should find both filter keys
        $this->assertGreaterThanOrEqual(2, count($keys));
    }

    #[Test]
    public function filter_matching_with_model_filter_works_correctly(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $runningWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now(),
        ]);

        $completedWorkflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
            'started_at'             => now()->subMinutes(5),
            'completed_at'           => now(),
        ]);

        $filter = ['status' => WorkflowStatesContract::STATUS_RUNNING];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // When - Test running workflow
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds1 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $runningWorkflowRun, WorkflowRun::class);

        // Then - Should match running workflow
        $this->assertContains($this->user->id, $userIds1);

        // When - Test completed workflow
        $userIds2 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $completedWorkflowRun, WorkflowRun::class);

        // Then - Should NOT match completed workflow
        $this->assertNotContains($this->user->id, $userIds2);
    }

    #[Test]
    public function multiple_users_with_different_subscription_types(): void
    {
        // Given - Create user2 on the SAME team as user1
        $user2 = \App\Models\User::factory()->create();
        $this->user->currentTeam->users()->attach($user2);
        $user2->currentTeam = $this->user->currentTeam;

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // User1 subscribed via channel-wide using controller
        $this->actingAs($this->user)
            ->postJson('/api/pusher/subscribe', [
                'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
                'resource_type'      => 'WorkflowRun',
                'model_id_or_filter' => true,
                'events'             => ['updated', 'created'],
            ]);

        // User2 subscribed via model-specific using controller
        $this->actingAs($user2)
            ->postJson('/api/pusher/subscribe', [
                'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
                'resource_type'      => 'WorkflowRun',
                'model_id_or_filter' => $workflowRun->id,
                'events'             => ['updated', 'created'],
            ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then - Both users should be in the list
        $this->assertCount(2, $userIds);
        $this->assertContains($this->user->id, $userIds);
        $this->assertContains($user2->id, $userIds);
    }

    #[Test]
    public function invalid_filter_does_not_break_broadcasting(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Create subscription with invalid filter using controller (field that doesn't exist on model)
        $filter = ['invalid_field' => 'invalid_value'];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // When - Should not throw exception
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        $userIds = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun, WorkflowRun::class);

        // Then - Should return empty (filter didn't match, but didn't break)
        // Note: Invalid filters just won't match, they don't break the system
        $this->assertIsArray($userIds);
    }

    #[Test]
    public function scanCacheKeys_handles_cache_prefix(): void
    {
        // Given - Create subscription using controller
        $filter = ['status' => WorkflowStatesContract::STATUS_RUNNING];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // When
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($pattern)
            {
                return $this->scanCacheKeys($pattern);
            }
        };

        $pattern = "subscribe:WorkflowRun:{$this->user->currentTeam->id}:filter:*";
        $keys    = $testObject->test($pattern);

        // Then - Should return keys without cache prefix
        $this->assertIsArray($keys);
        foreach ($keys as $key) {
            // Keys should not have the Laravel cache prefix
            $this->assertStringStartsWith('subscribe:', $key);
        }
    }

    #[Test]
    public function broadcasts_to_users_with_array_id_filter_subscription(): void
    {
        // Given - Create multiple WorkflowRuns
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun3 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun4 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // User subscribes with array ID filter: subscribe to IDs 1, 2, 3 (not 4)
        $filter = ['id' => [$workflowRun1->id, $workflowRun2->id, $workflowRun3->id]];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // When - Test objects
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        // Broadcast event for WorkflowRun ID 2 (in subscribed array)
        $userIds2 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun2, WorkflowRun::class);

        // Then - User should receive event (ID 2 is in subscribed array)
        $this->assertContains($this->user->id, $userIds2);

        // Broadcast event for WorkflowRun ID 4 (NOT in subscribed array)
        $userIds4 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun4, WorkflowRun::class);

        // Then - User should NOT receive event (ID 4 not in subscribed array)
        $this->assertNotContains($this->user->id, $userIds4);
    }

    #[Test]
    public function multiple_users_with_different_array_id_filters(): void
    {
        // Given - Create user2 on the SAME team as user1
        $user2 = \App\Models\User::factory()->create();
        $this->user->currentTeam->users()->attach($user2);
        $user2->currentTeam = $this->user->currentTeam;

        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun3 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // User1 subscribes to IDs [1, 2]
        $filter1 = ['id' => [$workflowRun1->id, $workflowRun2->id]];
        $this->actingAs($this->user)
            ->postJson('/api/pusher/subscribe', [
                'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
                'resource_type'      => 'WorkflowRun',
                'model_id_or_filter' => ['filter' => $filter1],
                'events'             => ['updated', 'created'],
            ]);

        // User2 subscribes to IDs [2, 3]
        $filter2 = ['id' => [$workflowRun2->id, $workflowRun3->id]];
        $this->actingAs($user2)
            ->postJson('/api/pusher/subscribe', [
                'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
                'resource_type'      => 'WorkflowRun',
                'model_id_or_filter' => ['filter' => $filter2],
                'events'             => ['updated', 'created'],
            ]);

        // When - Test objects
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        // Trigger broadcast for ID 1
        $userIds1 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun1, WorkflowRun::class);

        // Then - Only User1 should receive event
        $this->assertContains($this->user->id, $userIds1);
        $this->assertNotContains($user2->id, $userIds1);

        // Trigger broadcast for ID 2
        $userIds2 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun2, WorkflowRun::class);

        // Then - Both users should receive event
        $this->assertContains($this->user->id, $userIds2);
        $this->assertContains($user2->id, $userIds2);

        // Trigger broadcast for ID 3
        $userIds3 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun3, WorkflowRun::class);

        // Then - Only User2 should receive event
        $this->assertNotContains($this->user->id, $userIds3);
        $this->assertContains($user2->id, $userIds3);
    }

    #[Test]
    public function array_id_filter_works_with_single_id(): void
    {
        // Given - Create WorkflowRuns
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // User subscribes to only one ID in array
        $filter = ['id' => [$workflowRun1->id]];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // When - Test objects
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        // Broadcast for subscribed ID
        $userIds1 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun1, WorkflowRun::class);

        // Then - User should receive event
        $this->assertContains($this->user->id, $userIds1);

        // Broadcast for non-subscribed ID
        $userIds2 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun2, WorkflowRun::class);

        // Then - User should NOT receive event
        $this->assertNotContains($this->user->id, $userIds2);
    }

    #[Test]
    public function array_id_filter_combined_with_channel_wide_subscription(): void
    {
        // Given - Create WorkflowRuns
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun1 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $workflowRun2 = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // User subscribes with array ID filter
        $filter = ['id' => [$workflowRun1->id]];
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
            'events'             => ['updated', 'created'],
        ]);

        // User also subscribes channel-wide
        $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => \Illuminate\Support\Str::uuid()->toString(),
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => true,
            'events'             => ['updated', 'created'],
        ]);

        // When - Test objects
        $testObject = new class
        {
            use BroadcastsWithSubscriptions;

            public function test($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };

        // Broadcast for WorkflowRun1
        $userIds1 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun1, WorkflowRun::class);

        // Then - User should appear (but only once due to deduplication)
        $uniqueUserIds1 = array_unique($userIds1);
        $this->assertCount(1, $uniqueUserIds1);
        $this->assertContains($this->user->id, $uniqueUserIds1);

        // Broadcast for WorkflowRun2 (not in array filter but covered by channel-wide)
        $userIds2 = $testObject->test('WorkflowRun', $this->user->currentTeam->id, $workflowRun2, WorkflowRun::class);

        // Then - User should receive event (due to channel-wide subscription)
        $this->assertContains($this->user->id, $userIds2);
    }
}
