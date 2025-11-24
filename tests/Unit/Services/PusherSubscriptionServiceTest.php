<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\PusherSubscriptionService;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PusherSubscriptionServiceTest extends TestCase
{
    private PusherSubscriptionService $service;

    private int $teamId;

    private int $userId;

    public function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new PusherSubscriptionService();
        $this->teamId  = 1;
        $this->userId  = 100;
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    #[Test]
    public function subscribe_with_channel_wide_true_creates_correct_cache_entry(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';

        // When
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $cacheKey    = "subscribe:WorkflowRun:{$this->teamId}:all";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertCount(1, $subscribers);
    }

    #[Test]
    public function subscribe_with_model_id_creates_correct_cache_key_format(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440001';

        // When
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $cacheKey    = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertCount(1, $subscribers);
    }

    #[Test]
    public function subscribe_with_filter_object_creates_cache_and_definition_entries(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440002';
        $filter         = [
            'jobDispatchables.model_type' => 'App\Models\Workflow\WorkflowRun',
            'jobDispatchables.model_id'   => 5,
        ];

        // When
        $this->service->subscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then - Calculate expected hash
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey      = "subscribe:JobDispatch:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $subscribers  = Cache::get($cacheKey);
        $storedFilter = Cache::get($definitionKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertEquals($filter, $storedFilter);
    }

    #[Test]
    public function subscribe_prevents_duplicate_user_entries(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440003';

        // When - Subscribe twice with same parameters
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then - User should only appear once
        $cacheKey    = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $subscribers = Cache::get($cacheKey);

        $this->assertCount(1, $subscribers);
        $this->assertEquals([$this->userId], $subscribers);
    }

    #[Test]
    public function subscribe_adds_cache_key_to_index(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440004';

        // When
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:all";
        $this->assertContains($cacheKey, $index);
    }

    #[Test]
    public function unsubscribe_removes_user_from_cache(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440005';
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $this->assertTrue(Cache::has($cacheKey));

        // When
        $this->service->unsubscribe('WorkflowRun', 123, $this->teamId, $this->userId);

        // Then - Cache key should be deleted when last user unsubscribes
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function unsubscribe_deletes_cache_key_when_last_user_removed(): void
    {
        // Given - Two users subscribe
        $subscriptionId1 = '550e8400-e29b-41d4-a716-446655440006';
        $subscriptionId2 = '550e8400-e29b-41d4-a716-446655440007';
        $userId2         = 200;

        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId1);
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $userId2, ['updated'], $subscriptionId2);

        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:123";

        // When - First user unsubscribes
        $this->service->unsubscribe('WorkflowRun', 123, $this->teamId, $this->userId);

        // Then - Cache key should still exist with user2
        $this->assertTrue(Cache::has($cacheKey));
        $subscribers = Cache::get($cacheKey);
        $this->assertCount(1, $subscribers);
        $this->assertEquals([$userId2], $subscribers);

        // When - Second user unsubscribes
        $this->service->unsubscribe('WorkflowRun', 123, $this->teamId, $userId2);

        // Then - Cache key should be deleted
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function unsubscribe_keeps_other_users_in_cache(): void
    {
        // Given
        $subscriptionId1 = '550e8400-e29b-41d4-a716-446655440008';
        $subscriptionId2 = '550e8400-e29b-41d4-a716-446655440009';
        $userId2         = 200;

        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId, ['updated'], $subscriptionId1);
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $userId2, ['updated'], $subscriptionId2);

        // When
        $this->service->unsubscribe('WorkflowRun', true, $this->teamId, $this->userId);

        // Then
        $cacheKey    = "subscribe:WorkflowRun:{$this->teamId}:all";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertCount(1, $subscribers);
        $this->assertNotContains($this->userId, $subscribers);
        $this->assertContains($userId2, $subscribers);
    }

    #[Test]
    public function unsubscribe_removes_filter_definition_when_last_user_removed(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-44665544000a';
        $filter         = ['status' => 'Running'];

        $this->service->subscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId, ['updated'], $subscriptionId);

        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey      = "subscribe:JobDispatch:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($definitionKey));

        // When
        $this->service->unsubscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId);

        // Then
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($definitionKey));
    }

    #[Test]
    public function unsubscribe_removes_cache_key_from_index(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-44665544000b';
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:all";
        $indexKey = 'subscribe:_index';

        $this->assertContains($cacheKey, Cache::get($indexKey, []));

        // When
        $this->service->unsubscribe('WorkflowRun', true, $this->teamId, $this->userId);

        // Then
        $index = Cache::get($indexKey, []);
        $this->assertNotContains($cacheKey, $index);
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_null(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter cannot be empty');

        $this->service->validateModelIdOrFilter(null);
    }

    #[Test]
    public function validateModelIdOrFilter_accepts_integer(): void
    {
        // Should not throw
        $this->service->validateModelIdOrFilter(123);
        $this->assertTrue(true);
    }

    #[Test]
    public function validateModelIdOrFilter_accepts_true(): void
    {
        // Should not throw
        $this->service->validateModelIdOrFilter(true);
        $this->assertTrue(true);
    }

    #[Test]
    public function validateModelIdOrFilter_accepts_array_with_filter_key(): void
    {
        // Should not throw
        $this->service->validateModelIdOrFilter(['filter' => ['status' => 'Running']]);
        $this->assertTrue(true);
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_false(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter cannot be empty');

        $this->service->validateModelIdOrFilter(false);
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_empty_string(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter cannot be empty');

        $this->service->validateModelIdOrFilter('');
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_empty_array(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter cannot be empty');

        $this->service->validateModelIdOrFilter([]);
    }

    #[Test]
    public function validateModelIdOrFilter_accepts_string_id(): void
    {
        // Should not throw - string IDs are now valid
        $this->service->validateModelIdOrFilter('9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d');
        $this->assertTrue(true);
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_whitespace_only_string(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter string cannot be empty or whitespace only');

        $this->service->validateModelIdOrFilter('   ');
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_string_exceeding_255_chars(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter string cannot exceed 255 characters');

        $longString = str_repeat('a', 256);
        $this->service->validateModelIdOrFilter($longString);
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_array_without_filter_key(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter must be an integer, a string, true, or an object with a filter key');

        $this->service->validateModelIdOrFilter(['status' => 'Running']);
    }

    #[Test]
    public function buildCacheKey_formats_channel_wide_correctly(): void
    {
        $key = $this->service->buildCacheKey('WorkflowRun', $this->teamId, true);

        $this->assertEquals("subscribe:WorkflowRun:{$this->teamId}:all", $key);
    }

    #[Test]
    public function buildCacheKey_formats_model_id_correctly(): void
    {
        $key = $this->service->buildCacheKey('WorkflowRun', $this->teamId, 123);

        $this->assertEquals("subscribe:WorkflowRun:{$this->teamId}:id:123", $key);
    }

    #[Test]
    public function buildCacheKey_formats_string_id_correctly(): void
    {
        $uuid = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';
        $key  = $this->service->buildCacheKey('StoredFile', $this->teamId, $uuid);

        $this->assertEquals("subscribe:StoredFile:{$this->teamId}:id:{$uuid}", $key);
    }

    #[Test]
    public function buildCacheKey_formats_filter_correctly(): void
    {
        $filter = ['status' => 'Running', 'workflow_run_id' => 10];

        $key = $this->service->buildCacheKey('TaskRun', $this->teamId, ['filter' => $filter]);

        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $this->assertEquals("subscribe:TaskRun:{$this->teamId}:filter:{$hash}", $key);
    }

    #[Test]
    public function hashFilter_is_order_independent(): void
    {
        $filter1 = [
            'status'          => 'Running',
            'workflow_run_id' => 10,
        ];

        $filter2 = [
            'workflow_run_id' => 10,
            'status'          => 'Running',
        ];

        $hash1 = $this->service->hashFilter($filter1);
        $hash2 = $this->service->hashFilter($filter2);

        $this->assertEquals($hash1, $hash2, 'Hashes should be identical regardless of key order');
    }

    #[Test]
    public function hashFilter_handles_nested_arrays(): void
    {
        $filter1 = [
            'nested' => [
                'b' => 2,
                'a' => 1,
            ],
        ];

        $filter2 = [
            'nested' => [
                'a' => 1,
                'b' => 2,
            ],
        ];

        $hash1 = $this->service->hashFilter($filter1);
        $hash2 = $this->service->hashFilter($filter2);

        $this->assertEquals($hash1, $hash2, 'Hashes should be identical for nested arrays regardless of order');
    }

    #[Test]
    public function sortArrayRecursively_sorts_keys_at_all_levels(): void
    {
        $array = [
            'z'      => 'last',
            'a'      => 'first',
            'nested' => [
                'y' => 2,
                'x' => 1,
            ],
        ];

        $sorted = $this->service->sortArrayRecursively($array);

        $keys = array_keys($sorted);
        $this->assertEquals(['a', 'nested', 'z'], $keys);

        $nestedKeys = array_keys($sorted['nested']);
        $this->assertEquals(['x', 'y'], $nestedKeys);
    }

    #[Test]
    public function addToSubscriptionIndex_adds_new_keys(): void
    {
        $cacheKey1 = 'subscribe:WorkflowRun:1:id:123';
        $cacheKey2 = 'subscribe:WorkflowRun:1:id:456';

        $this->service->addToSubscriptionIndex($cacheKey1);
        $this->service->addToSubscriptionIndex($cacheKey2);

        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        $this->assertContains($cacheKey1, $index);
        $this->assertContains($cacheKey2, $index);
    }

    #[Test]
    public function addToSubscriptionIndex_prevents_duplicate_entries(): void
    {
        $cacheKey = 'subscribe:WorkflowRun:1:id:123';

        $this->service->addToSubscriptionIndex($cacheKey);
        $this->service->addToSubscriptionIndex($cacheKey);

        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        $this->assertCount(1, array_keys($index, $cacheKey));
    }

    #[Test]
    public function removeFromSubscriptionIndex_removes_keys(): void
    {
        $cacheKey1 = 'subscribe:WorkflowRun:1:id:123';
        $cacheKey2 = 'subscribe:WorkflowRun:1:id:456';

        $this->service->addToSubscriptionIndex($cacheKey1);
        $this->service->addToSubscriptionIndex($cacheKey2);

        $this->service->removeFromSubscriptionIndex($cacheKey1);

        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        $this->assertNotContains($cacheKey1, $index);
        $this->assertContains($cacheKey2, $index);
    }

    #[Test]
    public function removeFromSubscriptionIndex_deletes_index_when_empty(): void
    {
        $cacheKey = 'subscribe:WorkflowRun:1:id:123';

        $this->service->addToSubscriptionIndex($cacheKey);
        $this->service->removeFromSubscriptionIndex($cacheKey);

        $indexKey = 'subscribe:_index';

        $this->assertFalse(Cache::has($indexKey));
    }

    #[Test]
    public function subscribe_with_string_id_creates_correct_cache_key_format(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-44665544000c';
        $uuid           = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';

        // When
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $cacheKey    = "subscribe:StoredFile:{$this->teamId}:id:{$uuid}";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertCount(1, $subscribers);
    }

    #[Test]
    public function subscribe_with_string_id_adds_cache_key_to_index(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-44665544000d';
        $uuid           = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';

        // When
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        $cacheKey = "subscribe:StoredFile:{$this->teamId}:id:{$uuid}";
        $this->assertContains($cacheKey, $index);
    }

    #[Test]
    public function unsubscribe_with_string_id_removes_user_from_cache(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-44665544000e';
        $uuid           = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        $cacheKey = "subscribe:StoredFile:{$this->teamId}:id:{$uuid}";
        $this->assertTrue(Cache::has($cacheKey));

        // When
        $this->service->unsubscribe('StoredFile', $uuid, $this->teamId, $this->userId);

        // Then - Cache key should be deleted when last user unsubscribes
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function unsubscribe_with_string_id_removes_cache_key_from_index(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-44665544000f';
        $uuid           = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        $cacheKey = "subscribe:StoredFile:{$this->teamId}:id:{$uuid}";
        $indexKey = 'subscribe:_index';

        $this->assertContains($cacheKey, Cache::get($indexKey, []));

        // When
        $this->service->unsubscribe('StoredFile', $uuid, $this->teamId, $this->userId);

        // Then
        $index = Cache::get($indexKey, []);
        $this->assertNotContains($cacheKey, $index);
    }

    #[Test]
    public function subscribe_with_string_id_prevents_duplicate_user_entries(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440010';
        $uuid           = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';

        // When - Subscribe twice with same parameters
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId);
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then - User should only appear once
        $cacheKey    = "subscribe:StoredFile:{$this->teamId}:id:{$uuid}";
        $subscribers = Cache::get($cacheKey);

        $this->assertCount(1, $subscribers);
        $this->assertEquals([$this->userId], $subscribers);
    }

    #[Test]
    public function unsubscribe_with_string_id_keeps_other_users_in_cache(): void
    {
        // Given
        $subscriptionId1 = '550e8400-e29b-41d4-a716-446655440011';
        $subscriptionId2 = '550e8400-e29b-41d4-a716-446655440012';
        $uuid            = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';
        $userId2         = 200;

        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $this->userId, ['updated'], $subscriptionId1);
        $this->service->subscribe('StoredFile', $uuid, $this->teamId, $userId2, ['updated'], $subscriptionId2);

        // When
        $this->service->unsubscribe('StoredFile', $uuid, $this->teamId, $this->userId);

        // Then
        $cacheKey    = "subscribe:StoredFile:{$this->teamId}:id:{$uuid}";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertCount(1, $subscribers);
        $this->assertNotContains($this->userId, $subscribers);
        $this->assertContains($userId2, $subscribers);
    }

    #[Test]
    public function subscribe_supports_both_numeric_and_string_ids_simultaneously(): void
    {
        // Given
        $subscriptionId1 = '550e8400-e29b-41d4-a716-446655440013';
        $subscriptionId2 = '550e8400-e29b-41d4-a716-446655440014';
        $numericId       = 123;
        $stringId        = '9d3f5a2b-8c4e-4d1a-9b2c-1e3f4a5b6c7d';

        // When
        $this->service->subscribe('WorkflowRun', $numericId, $this->teamId, $this->userId, ['updated'], $subscriptionId1);
        $this->service->subscribe('StoredFile', $stringId, $this->teamId, $this->userId, ['updated'], $subscriptionId2);

        // Then
        $numericCacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:{$numericId}";
        $stringCacheKey  = "subscribe:StoredFile:{$this->teamId}:id:{$stringId}";

        $this->assertTrue(Cache::has($numericCacheKey));
        $this->assertTrue(Cache::has($stringCacheKey));

        $numericSubscribers = Cache::get($numericCacheKey);
        $stringSubscribers  = Cache::get($stringCacheKey);

        $this->assertContains($this->userId, $numericSubscribers);
        $this->assertContains($this->userId, $stringSubscribers);
    }

    #[Test]
    public function subscribe_accepts_and_returns_provided_subscription_id(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';

        // When
        $result = $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals($subscriptionId, $result['subscription']['id']);
    }

    #[Test]
    public function subscribe_stores_subscription_metadata_in_cache(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';
        $events         = ['created', 'updated'];

        // When
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, $events, $subscriptionId);

        // Then
        $metadataKey = "subscription:id:{$subscriptionId}";
        $metadata    = Cache::get($metadataKey);

        $this->assertIsArray($metadata);
        $this->assertEquals($subscriptionId, $metadata['id']);
        $this->assertEquals('WorkflowRun', $metadata['resource_type']);
        $this->assertEquals(123, $metadata['model_id_or_filter']);
        $this->assertEquals($events, $metadata['events']);
        $this->assertEquals($this->teamId, $metadata['team_id']);
        $this->assertEquals($this->userId, $metadata['user_id']);
        $this->assertArrayHasKey('cache_key', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
    }

    #[Test]
    public function subscribe_returns_iso8601_expiration_timestamp(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440015';

        // When
        $result = $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then
        $expiresAt = $result['subscription']['expires_at'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $expiresAt);
    }

    #[Test]
    public function keepaliveByIds_refreshes_subscription_and_returns_updated_expiration(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // When
        $results = $this->service->keepaliveByIds([$subscriptionId], $this->userId);

        // Then
        $this->assertArrayHasKey($subscriptionId, $results);
        $this->assertTrue($results[$subscriptionId]['success']);
        $this->assertArrayHasKey('expires_at', $results[$subscriptionId]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $results[$subscriptionId]['expires_at']);
    }

    #[Test]
    public function keepaliveByIds_handles_multiple_subscription_ids(): void
    {
        // Given
        $subscriptionId1 = '550e8400-e29b-41d4-a716-446655440000';
        $subscriptionId2 = '660e8400-e29b-41d4-a716-446655440000';

        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId1);
        $this->service->subscribe('TaskRun', true, $this->teamId, $this->userId, ['updated'], $subscriptionId2);

        // When
        $results = $this->service->keepaliveByIds([$subscriptionId1, $subscriptionId2], $this->userId);

        // Then
        $this->assertCount(2, $results);
        $this->assertTrue($results[$subscriptionId1]['success']);
        $this->assertTrue($results[$subscriptionId2]['success']);
        $this->assertArrayHasKey('expires_at', $results[$subscriptionId1]);
        $this->assertArrayHasKey('expires_at', $results[$subscriptionId2]);
    }

    #[Test]
    public function keepaliveByIds_returns_error_for_non_existent_subscription(): void
    {
        // Given
        $nonExistentId = '999e8400-e29b-41d4-a716-446655440000';

        // When
        $results = $this->service->keepaliveByIds([$nonExistentId], $this->userId);

        // Then
        $this->assertArrayHasKey($nonExistentId, $results);
        $this->assertFalse($results[$nonExistentId]['success']);
        $this->assertEquals('Subscription not found or expired', $results[$nonExistentId]['error']);
    }

    #[Test]
    public function keepaliveByIds_returns_error_for_unauthorized_user(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';
        $otherUserId    = 999;

        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // When - Different user tries to keepalive
        $results = $this->service->keepaliveByIds([$subscriptionId], $otherUserId);

        // Then
        $this->assertArrayHasKey($subscriptionId, $results);
        $this->assertFalse($results[$subscriptionId]['success']);
        $this->assertEquals('Unauthorized', $results[$subscriptionId]['error']);
    }

    #[Test]
    public function keepaliveByIds_returns_error_when_user_not_in_subscribers_list(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';

        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Manually remove user from subscribers list to simulate edge case
        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        Cache::put($cacheKey, [], 300);

        // When
        $results = $this->service->keepaliveByIds([$subscriptionId], $this->userId);

        // Then
        $this->assertArrayHasKey($subscriptionId, $results);
        $this->assertFalse($results[$subscriptionId]['success']);
        $this->assertEquals('User not subscribed', $results[$subscriptionId]['error']);
    }

    #[Test]
    public function keepaliveByIds_refreshes_filter_based_subscriptions(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';
        $filter         = ['status' => 'Running'];

        $this->service->subscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // When
        $results = $this->service->keepaliveByIds([$subscriptionId], $this->userId);

        // Then
        $this->assertTrue($results[$subscriptionId]['success']);

        // Verify filter definition still exists and was refreshed
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey      = "subscribe:JobDispatch:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $this->assertTrue(Cache::has($definitionKey));
    }

    #[Test]
    public function keepaliveByIds_updates_metadata_expiration_timestamp(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440000';
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Get original expiration
        $metadataKey        = "subscription:id:{$subscriptionId}";
        $originalMetadata   = Cache::get($metadataKey);
        $originalExpiresAt  = $originalMetadata['expires_at'];

        // Wait a moment to ensure timestamp difference
        sleep(1);

        // When
        $results = $this->service->keepaliveByIds([$subscriptionId], $this->userId);

        // Then
        $updatedMetadata  = Cache::get($metadataKey);
        $updatedExpiresAt = $updatedMetadata['expires_at'];

        $this->assertNotEquals($originalExpiresAt, $updatedExpiresAt);
        $this->assertEquals($updatedExpiresAt, $results[$subscriptionId]['expires_at']);
    }

    #[Test]
    public function keepaliveByIds_handles_mixed_success_and_failure_results(): void
    {
        // Given
        $validSubscriptionId   = '550e8400-e29b-41d4-a716-446655440000';
        $invalidSubscriptionId = '999e8400-e29b-41d4-a716-446655440000';

        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, ['updated'], $validSubscriptionId);

        // When
        $results = $this->service->keepaliveByIds([$validSubscriptionId, $invalidSubscriptionId], $this->userId);

        // Then
        $this->assertCount(2, $results);
        $this->assertTrue($results[$validSubscriptionId]['success']);
        $this->assertFalse($results[$invalidSubscriptionId]['success']);
    }

    #[Test]
    public function subscribe_with_custom_events_array(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440016';
        $customEvents   = ['created', 'updated', 'deleted'];

        // When
        $result = $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId, $customEvents, $subscriptionId);

        // Then
        $this->assertEquals($customEvents, $result['subscription']['events']);

        // Verify stored in metadata
        $metadataKey = "subscription:id:{$result['subscription']['id']}";
        $metadata    = Cache::get($metadataKey);
        $this->assertEquals($customEvents, $metadata['events']);
    }

    #[Test]
    public function subscribe_with_array_id_filter_creates_correct_cache_entry(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440017';
        $filter         = ['id' => [1, 2, 3]];

        // When
        $this->service->subscribe('WorkflowRun', ['filter' => $filter], $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then - Calculate expected hash
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey      = "subscribe:WorkflowRun:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $subscribers  = Cache::get($cacheKey);
        $storedFilter = Cache::get($definitionKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertEquals($filter, $storedFilter);
        $this->assertIsArray($storedFilter['id']);
        $this->assertEquals([1, 2, 3], $storedFilter['id']);
    }

    #[Test]
    public function subscribe_with_array_id_filter_stores_subscription_metadata(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440018';
        $filter         = ['id' => [10, 20, 30, 40]];
        $events         = ['created', 'updated'];

        // When
        $result = $this->service->subscribe('TaskRun', ['filter' => $filter], $this->teamId, $this->userId, $events, $subscriptionId);

        // Then
        $metadataKey = "subscription:id:{$subscriptionId}";
        $metadata    = Cache::get($metadataKey);

        $this->assertIsArray($metadata);
        $this->assertEquals($subscriptionId, $metadata['id']);
        $this->assertEquals('TaskRun', $metadata['resource_type']);
        $this->assertEquals(['filter' => $filter], $metadata['model_id_or_filter']);
        $this->assertEquals($events, $metadata['events']);
        $this->assertEquals($this->teamId, $metadata['team_id']);
        $this->assertEquals($this->userId, $metadata['user_id']);
        $this->assertArrayHasKey('cache_key', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
    }

    #[Test]
    public function buildCacheKey_formats_array_id_filter_correctly(): void
    {
        // Given
        $filter = ['id' => [100, 200, 300]];

        // When
        $key = $this->service->buildCacheKey('WorkflowRun', $this->teamId, ['filter' => $filter]);

        // Then
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $this->assertEquals("subscribe:WorkflowRun:{$this->teamId}:filter:{$hash}", $key);
    }

    #[Test]
    public function hashFilter_with_array_id_is_order_independent(): void
    {
        // Given - Same IDs in different order
        $filter1 = ['id' => [1, 2, 3]];
        $filter2 = ['id' => [3, 2, 1]];

        // When
        $hash1 = $this->service->hashFilter($filter1);
        $hash2 = $this->service->hashFilter($filter2);

        // Then - Hashes should be different because array values are ordered
        // (This is expected behavior - array value order matters)
        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function unsubscribe_with_array_id_filter_removes_user_from_cache(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440019';
        $filter         = ['id' => [5, 10, 15]];

        $this->service->subscribe('TaskProcess', ['filter' => $filter], $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // Then - Verify subscription was created
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey = "subscribe:TaskProcess:{$this->teamId}:filter:{$hash}";
        $this->assertTrue(Cache::has($cacheKey));

        // When
        $this->service->unsubscribe('TaskProcess', ['filter' => $filter], $this->teamId, $this->userId);

        // Then - Cache key should be deleted when last user unsubscribes
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function keepaliveByIds_refreshes_array_id_filter_subscriptions(): void
    {
        // Given
        $subscriptionId = '550e8400-e29b-41d4-a716-446655440020';
        $filter         = ['id' => [7, 8, 9]];

        $this->service->subscribe('WorkflowRun', ['filter' => $filter], $this->teamId, $this->userId, ['updated'], $subscriptionId);

        // When
        $results = $this->service->keepaliveByIds([$subscriptionId], $this->userId);

        // Then
        $this->assertTrue($results[$subscriptionId]['success']);

        // Verify filter definition still exists and was refreshed
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey      = "subscribe:WorkflowRun:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $this->assertTrue(Cache::has($definitionKey));
        $this->assertEquals($filter, Cache::get($definitionKey));
    }
}
