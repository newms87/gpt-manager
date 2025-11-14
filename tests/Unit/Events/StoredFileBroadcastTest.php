<?php

namespace Tests\Unit\Events;

use App\Models\Task\Artifact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Events\StoredFileUpdatedEvent;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class StoredFileBroadcastTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private $testBroadcastObject;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        Cache::flush();

        // Create test object once for all tests
        $this->testBroadcastObject = new class
        {
            use \Newms87\Danx\Traits\BroadcastsWithSubscriptions;

            public function testGetSubscribedUsers($resourceType, $teamId, $model, $modelClass)
            {
                return $this->getSubscribedUsers($resourceType, $teamId, $model, $modelClass);
            }
        };
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Helper method to get subscribed users for a model
     */
    protected function getSubscribedUsersForModel($resourceType, $teamId, $model, $modelClass): array
    {
        return $this->testBroadcastObject->testGetSubscribedUsers($resourceType, $teamId, $model, $modelClass);
    }

    #[Test]
    public function stored_file_broadcasts_with_subscription_full_flow(): void
    {
        // =====================================================================
        // SETUP: Create team, user, and models
        // =====================================================================

        Log::info('=== TEST START: StoredFile Broadcast with Subscriptions ===');
        Log::info('Test Setup', [
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create an Artifact with team_id (storable model)
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Artifact for StoredFile',
        ]);

        Log::info('Created Artifact', [
            'artifact_id'      => $artifact->id,
            'artifact_team_id' => $artifact->team_id,
        ]);

        // Create a StoredFile attached to the Artifact
        $storedFile = StoredFile::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'filename'       => 'test-video.mp4',
            'mime'           => StoredFile::MIME_MP4,
            'meta'           => [
                'transcodes' => [
                    ['status' => 'pending', 'name' => 'web-optimized'],
                ],
            ],
            'is_transcoding' => true,
        ]);

        // Attach the StoredFile to the Artifact via morphToMany relationship
        $artifact->storedFiles()->attach($storedFile->id);
        $storedFile->refresh();

        Log::info('Created StoredFile attached to Artifact', [
            'stored_file_id' => $storedFile->id,
            'filename'       => $storedFile->filename,
        ]);

        // Check pivot table to understand the relationship
        $pivot = \DB::table('stored_file_storables')
            ->where('stored_file_id', $storedFile->id)
            ->first();

        Log::info('Pivot table data', [
            'pivot' => $pivot ? (array)$pivot : 'null',
        ]);

        // Load the storable relationship to test team_id accessor
        // Since morphToMany uses pivot table, we need to access it differently
        $storables = $artifact->storedFiles()->get();
        Log::info('Storables from Artifact', [
            'count'    => $storables->count(),
            'first_id' => $storables->first()?->id,
        ]);

        // For StoredFile, the storable() morphTo relationship won't work with pivot table
        // The team_id accessor needs to query pivot table and load the storable
        // Let's test what team_id we get
        $teamIdFromAccessor = $storedFile->team_id;
        Log::info('StoredFile team_id accessor result', [
            'team_id'          => $teamIdFromAccessor,
            'expected_team_id' => $this->user->currentTeam->id,
        ]);

        // NOTE: The team_id accessor may return null because storable() morphTo doesn't work with pivot table
        // This is the ROOT CAUSE of why broadcasts aren't working!

        // =====================================================================
        // SUBSCRIBE: User subscribes to StoredFile updates for this specific file
        // =====================================================================

        Log::info('=== SUBSCRIBING USER TO STOREDFILE ===');

        // Subscribe to this specific StoredFile
        $subscriptionId = \Illuminate\Support\Str::uuid()->toString();
        $response       = $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => $subscriptionId,
            'resource_type'      => 'StoredFile',
            'model_id_or_filter' => $storedFile->id,
            'events'             => ['updated', 'created'],
        ]);

        Log::info('Subscription API Response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        $response->assertStatus(200);

        // Verify subscription was stored in cache
        $subscriptionKey = "subscribe:StoredFile:{$this->user->currentTeam->id}:id:{$storedFile->id}";
        $subscribers     = Cache::get($subscriptionKey, []);

        Log::info('Subscription Cache Check', [
            'cache_key'     => $subscriptionKey,
            'subscribers'   => $subscribers,
            'contains_user' => in_array($this->user->id, $subscribers),
        ]);

        $this->assertContains($this->user->id, $subscribers, 'User should be in subscription cache');

        // =====================================================================
        // UPDATE: Simulate transcoding completion by updating meta field
        // =====================================================================

        Log::info('=== UPDATING STOREDFILE META (SIMULATING TRANSCODING COMPLETION) ===');

        // Set up event listener to capture broadcast details
        $eventFired       = false;
        $capturedEvent    = null;
        $capturedChannels = null;
        $capturedData     = null;

        Event::listen(StoredFileUpdatedEvent::class, function ($event) use (&$eventFired, &$capturedEvent, &$capturedChannels, &$capturedData, $storedFile) {
            $eventFired       = true;
            $capturedEvent    = $event;
            $capturedChannels = $event->broadcastOn();
            $capturedData     = $event->broadcastWith();

            // Reload the StoredFile to get updated team_id
            $storedFile->refresh();

            Log::info('=== StoredFileUpdatedEvent FIRED ===', [
                'event_class'         => get_class($event),
                'stored_file_id'      => $storedFile->id,
                'stored_file_team_id' => $storedFile->team_id,
                'channels_count'      => count($capturedChannels),
                'channels'            => array_map(fn($ch) => $ch->name, $capturedChannels),
                'data'                => $capturedData,
            ]);
        });

        // Update the StoredFile meta to simulate transcoding completion
        $storedFile->meta = [
            'transcodes' => [
                ['status' => 'completed', 'name' => 'web-optimized', 'url' => 'https://example.com/video-web.mp4'],
            ],
        ];
        $storedFile->is_transcoding = false;
        $storedFile->save();

        Log::info('StoredFile Updated', [
            'stored_file_id'   => $storedFile->id,
            'meta'             => $storedFile->meta,
            'is_transcoding'   => $storedFile->is_transcoding,
            'was_changed_meta' => $storedFile->wasChanged('meta'),
        ]);

        // =====================================================================
        // VERIFY: Check that event fired and broadcast correctly
        // =====================================================================

        Log::info('=== VERIFICATION PHASE ===');

        // 1. Verify event was fired
        $this->assertTrue($eventFired, 'StoredFileUpdatedEvent should have fired');
        $this->assertNotNull($capturedEvent, 'Event should have been captured');

        // 2. Verify event has correct team_id
        // Note: We can't access protected properties, but we can verify through the broadcast channels
        Log::info('Verifying Event broadcast channels contain team_id');

        // 3. Manually test getSubscribedUsers logic (what broadcastOn() calls internally)
        Log::info('=== MANUALLY TESTING getSubscribedUsers() ===');

        $subscribedUserIds = $this->getSubscribedUsersForModel(
            'StoredFile',
            $this->user->currentTeam->id,
            $storedFile,
            StoredFile::class
        );

        Log::info('getSubscribedUsers() Result', [
            'subscribed_user_ids'    => $subscribedUserIds,
            'expected_user_id'       => $this->user->id,
            'contains_expected_user' => in_array($this->user->id, $subscribedUserIds),
        ]);

        $this->assertContains($this->user->id, $subscribedUserIds, 'getSubscribedUsers should find the subscribed user');

        // 4. Verify broadcast channels were created correctly
        $this->assertNotEmpty($capturedChannels, 'Broadcast channels should not be empty');
        $this->assertCount(1, $capturedChannels, 'Should have exactly one broadcast channel');

        $expectedChannelName = "private-StoredFile.{$this->user->currentTeam->id}";
        $actualChannelName   = $capturedChannels[0]->name;

        Log::info('Channel Verification', [
            'expected_channel' => $expectedChannelName,
            'actual_channel'   => $actualChannelName,
            'matches'          => $expectedChannelName === $actualChannelName,
        ]);

        $this->assertEquals($expectedChannelName, $actualChannelName, 'Broadcast channel name should match expected format');

        // 5. Verify broadcast data contains updated information
        Log::info('Broadcast Data Structure', [
            'broadcast_data' => $capturedData,
            'keys'           => array_keys($capturedData),
        ]);

        // The broadcast data structure may be different - check what we actually have
        if (isset($capturedData['data'])) {
            $this->assertArrayHasKey('is_transcoding', $capturedData['data'], 'Broadcast data should contain is_transcoding');
            $this->assertFalse($capturedData['data']['is_transcoding'], 'is_transcoding should be false after completion');
        } else {
            // Data might be at root level
            $this->assertArrayHasKey('is_transcoding', $capturedData, 'Broadcast data should contain is_transcoding');
            $this->assertFalse($capturedData['is_transcoding'], 'is_transcoding should be false after completion');
        }

        Log::info('Broadcast Data Verification', [
            'broadcast_data'       => $capturedData,
            'is_transcoding_value' => $capturedData['is_transcoding'] ?? $capturedData['data']['is_transcoding'] ?? 'not set',
        ]);

        // =====================================================================
        // CACHE INSPECTION: Debug all relevant cache keys
        // =====================================================================

        Log::info('=== CACHE INSPECTION ===');

        // Check all StoredFile subscription cache keys
        $allCacheKey    = "subscribe:StoredFile:{$this->user->currentTeam->id}:all";
        $allSubscribers = Cache::get($allCacheKey, []);

        Log::info('Channel-wide subscriptions', [
            'cache_key'   => $allCacheKey,
            'subscribers' => $allSubscribers,
        ]);

        $modelSpecificKey = "subscribe:StoredFile:{$this->user->currentTeam->id}:id:{$storedFile->id}";
        $modelSubscribers = Cache::get($modelSpecificKey, []);

        Log::info('Model-specific subscriptions', [
            'cache_key'   => $modelSpecificKey,
            'subscribers' => $modelSubscribers,
        ]);

        Log::info('=== TEST END: All Verifications Passed ===');
    }

    #[Test]
    public function stored_file_without_storable_has_null_team_id(): void
    {
        Log::info('=== TEST: StoredFile without storable has null team_id ===');

        // Create a StoredFile NOT attached to any storable
        $storedFile = StoredFile::factory()->create([
            'filename' => 'orphan-file.pdf',
        ]);

        Log::info('Created orphan StoredFile', [
            'stored_file_id' => $storedFile->id,
            'storable_type'  => $storedFile->storable_type,
            'storable_id'    => $storedFile->storable_id,
        ]);

        // Verify team_id is null for orphan files
        $teamId = $storedFile->team_id;

        Log::info('Orphan StoredFile team_id', [
            'team_id' => $teamId,
            'is_null' => $teamId === null,
        ]);

        $this->assertNull($teamId, 'StoredFile without storable should have null team_id');

        // Verify no broadcast happens for files without team_id
        $eventFired       = false;
        $capturedChannels = [];
        Event::listen(StoredFileUpdatedEvent::class, function ($event) use (&$eventFired, &$capturedChannels) {
            $eventFired       = true;
            $capturedChannels = $event->broadcastOn();

            Log::info('Event fired for orphan file', [
                'channels' => array_map(fn($ch) => $ch->name, $capturedChannels),
            ]);
        });

        $storedFile->meta = ['test' => 'updated'];
        $storedFile->save();

        if ($eventFired) {
            Log::info('Event was fired, verifying channels are empty', [
                'channels_count' => count($capturedChannels),
            ]);
            // If event fires, verify it has no broadcast channels (because no team_id = no subscriptions)
            $this->assertEmpty($capturedChannels, 'Orphan file should have no broadcast channels');
        } else {
            Log::info('Event was not fired (expected behavior for orphan file)');
            $this->assertTrue(true, 'Event not fired for orphan file is acceptable');
        }
    }

    #[Test]
    public function channel_wide_subscription_receives_all_stored_file_updates(): void
    {
        Log::info('=== TEST: Channel-wide subscription receives all StoredFile updates ===');

        // Create an Artifact with StoredFile
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $storedFile = StoredFile::factory()->create(['filename' => 'test1.pdf']);
        $artifact->storedFiles()->attach($storedFile->id);
        $storedFile->refresh();

        Log::info('Setup complete', [
            'artifact_id'    => $artifact->id,
            'stored_file_id' => $storedFile->id,
            'team_id'        => $this->user->currentTeam->id,
        ]);

        // Subscribe to ALL StoredFiles (channel-wide)
        $subscriptionId = \Illuminate\Support\Str::uuid()->toString();
        $response       = $this->postJson('/api/pusher/subscribe', [
            'subscription_id'    => $subscriptionId,
            'resource_type'      => 'StoredFile',
            'model_id_or_filter' => true, // true = channel-wide
            'events'             => ['updated', 'created'],
        ]);

        Log::info('Channel-wide subscription created', [
            'response_status' => $response->status(),
        ]);

        $response->assertStatus(200);

        // Verify subscription
        $subscribedUserIds = $this->getSubscribedUsersForModel(
            'StoredFile',
            $this->user->currentTeam->id,
            $storedFile,
            StoredFile::class
        );

        Log::info('Channel-wide subscription check', [
            'subscribed_users' => $subscribedUserIds,
            'contains_user'    => in_array($this->user->id, $subscribedUserIds),
        ]);

        $this->assertContains($this->user->id, $subscribedUserIds, 'User should receive updates via channel-wide subscription');
    }

    #[Test]
    public function multiple_users_can_subscribe_to_same_stored_file(): void
    {
        Log::info('=== TEST: Multiple users can subscribe to same StoredFile ===');

        // Create second user on same team
        $user2 = \App\Models\User::factory()->create();
        $this->user->currentTeam->users()->attach($user2);
        $user2->currentTeam = $this->user->currentTeam;

        Log::info('Created second user', [
            'user1_id' => $this->user->id,
            'user2_id' => $user2->id,
            'team_id'  => $this->user->currentTeam->id,
        ]);

        // Create Artifact and StoredFile
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $storedFile = StoredFile::factory()->create(['filename' => 'shared.mp4']);
        $artifact->storedFiles()->attach($storedFile->id);
        $storedFile->refresh();

        Log::info('Created shared StoredFile', [
            'stored_file_id' => $storedFile->id,
        ]);

        // User 1 subscribes
        $subscriptionId1 = \Illuminate\Support\Str::uuid()->toString();
        $this->actingAs($this->user)
            ->postJson('/api/pusher/subscribe', [
                'subscription_id'    => $subscriptionId1,
                'resource_type'      => 'StoredFile',
                'model_id_or_filter' => $storedFile->id,
                'events'             => ['updated', 'created'],
            ])
            ->assertStatus(200);

        // User 2 subscribes
        $subscriptionId2 = \Illuminate\Support\Str::uuid()->toString();
        $this->actingAs($user2)
            ->postJson('/api/pusher/subscribe', [
                'subscription_id'    => $subscriptionId2,
                'resource_type'      => 'StoredFile',
                'model_id_or_filter' => $storedFile->id,
                'events'             => ['updated', 'created'],
            ])
            ->assertStatus(200);

        Log::info('Both users subscribed');

        // Verify both users are subscribed
        $subscribedUserIds = $this->getSubscribedUsersForModel(
            'StoredFile',
            $this->user->currentTeam->id,
            $storedFile,
            StoredFile::class
        );

        Log::info('Subscription check result', [
            'subscribed_users' => $subscribedUserIds,
            'expected_users'   => [$this->user->id, $user2->id],
        ]);

        $this->assertCount(2, $subscribedUserIds, 'Both users should be subscribed');
        $this->assertContains($this->user->id, $subscribedUserIds, 'User 1 should be subscribed');
        $this->assertContains($user2->id, $subscribedUserIds, 'User 2 should be subscribed');
    }
}
