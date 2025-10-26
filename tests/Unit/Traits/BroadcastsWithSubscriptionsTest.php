<?php

namespace Tests\Unit\Traits;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Traits\BroadcastsWithSubscriptions;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => true,
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => true,
        ]);
        $this->postJson('/api/pusher/subscribe', [
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => $workflowRun->id,
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter1],
        ]);

        $filter2 = ['status' => WorkflowStatesContract::STATUS_COMPLETED];
        $this->postJson('/api/pusher/subscribe', [
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter2],
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
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
                'resource_type'      => 'WorkflowRun',
                'model_id_or_filter' => true,
            ]);

        // User2 subscribed via model-specific using controller
        $this->actingAs($user2)
            ->postJson('/api/pusher/subscribe', [
                'resource_type'      => 'WorkflowRun',
                'model_id_or_filter' => $workflowRun->id,
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
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
            'resource_type'      => 'WorkflowRun',
            'model_id_or_filter' => ['filter' => $filter],
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
}
