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
        $this->teamId = 1;
        $this->userId = 100;
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    #[Test]
    public function subscribe_with_channel_wide_true_creates_correct_cache_entry(): void
    {
        // When
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId);

        // Then
        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:all";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertCount(1, $subscribers);
    }

    #[Test]
    public function subscribe_with_model_id_creates_correct_cache_key_format(): void
    {
        // When
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);

        // Then
        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $subscribers = Cache::get($cacheKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertCount(1, $subscribers);
    }

    #[Test]
    public function subscribe_with_filter_object_creates_cache_and_definition_entries(): void
    {
        // Given
        $filter = [
            'jobDispatchables.model_type' => 'App\Models\Workflow\WorkflowRun',
            'jobDispatchables.model_id' => 5,
        ];

        // When
        $this->service->subscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId);

        // Then - Calculate expected hash
        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey = "subscribe:JobDispatch:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $subscribers = Cache::get($cacheKey);
        $storedFilter = Cache::get($definitionKey);

        $this->assertIsArray($subscribers);
        $this->assertContains($this->userId, $subscribers);
        $this->assertEquals($filter, $storedFilter);
    }

    #[Test]
    public function subscribe_prevents_duplicate_user_entries(): void
    {
        // When - Subscribe twice with same parameters
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);

        // Then - User should only appear once
        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $subscribers = Cache::get($cacheKey);

        $this->assertCount(1, $subscribers);
        $this->assertEquals([$this->userId], $subscribers);
    }

    #[Test]
    public function subscribe_adds_cache_key_to_index(): void
    {
        // When
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId);

        // Then
        $indexKey = 'subscribe:_index';
        $index = Cache::get($indexKey, []);

        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:all";
        $this->assertContains($cacheKey, $index);
    }

    #[Test]
    public function unsubscribe_removes_user_from_cache(): void
    {
        // Given
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);

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
        $userId2 = 200;

        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $userId2);

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
        $userId2 = 200;

        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId);
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $userId2);

        // When
        $this->service->unsubscribe('WorkflowRun', true, $this->teamId, $this->userId);

        // Then
        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:all";
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
        $filter = ['status' => 'Running'];

        $this->service->subscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId);

        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey = "subscribe:JobDispatch:{$this->teamId}:filter:{$hash}";
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
        $this->service->subscribe('WorkflowRun', true, $this->teamId, $this->userId);

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
    public function keepalive_refreshes_ttl_for_active_subscriptions(): void
    {
        // Given
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);

        $subscriptions = [
            [
                'resource_type' => 'WorkflowRun',
                'model_id_or_filter' => 123,
            ],
        ];

        // When
        $refreshedCount = $this->service->keepalive($subscriptions, $this->teamId, $this->userId);

        // Then
        $this->assertEquals(1, $refreshedCount);

        $cacheKey = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[Test]
    public function keepalive_handles_multiple_subscriptions(): void
    {
        // Given
        $this->service->subscribe('WorkflowRun', 123, $this->teamId, $this->userId);
        $this->service->subscribe('TaskRun', true, $this->teamId, $this->userId);

        $subscriptions = [
            [
                'resource_type' => 'WorkflowRun',
                'model_id_or_filter' => 123,
            ],
            [
                'resource_type' => 'TaskRun',
                'model_id_or_filter' => true,
            ],
        ];

        // When
        $refreshedCount = $this->service->keepalive($subscriptions, $this->teamId, $this->userId);

        // Then
        $this->assertEquals(2, $refreshedCount);

        $cacheKey1 = "subscribe:WorkflowRun:{$this->teamId}:id:123";
        $cacheKey2 = "subscribe:TaskRun:{$this->teamId}:all";

        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));
    }

    #[Test]
    public function keepalive_skips_non_existent_subscriptions(): void
    {
        // When - Keepalive without subscribing first
        $subscriptions = [
            [
                'resource_type' => 'WorkflowRun',
                'model_id_or_filter' => 999,
            ],
        ];

        $refreshedCount = $this->service->keepalive($subscriptions, $this->teamId, $this->userId);

        // Then - Should return 0 (skipped)
        $this->assertEquals(0, $refreshedCount);
    }

    #[Test]
    public function keepalive_refreshes_filter_definition_ttl(): void
    {
        // Given
        $filter = ['status' => 'Running'];

        $this->service->subscribe('JobDispatch', ['filter' => $filter], $this->teamId, $this->userId);

        $sorted = $filter;
        ksort($sorted);
        $hash = md5(json_encode($sorted, JSON_UNESCAPED_SLASHES));

        $cacheKey = "subscribe:JobDispatch:{$this->teamId}:filter:{$hash}";
        $definitionKey = "{$cacheKey}:definition";

        $subscriptions = [
            [
                'resource_type' => 'JobDispatch',
                'model_id_or_filter' => ['filter' => $filter],
            ],
        ];

        // When
        $refreshedCount = $this->service->keepalive($subscriptions, $this->teamId, $this->userId);

        // Then
        $this->assertEquals(1, $refreshedCount);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($definitionKey));
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
    public function validateModelIdOrFilter_rejects_string(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter must be an integer, true, or an object with a filter key');

        $this->service->validateModelIdOrFilter('invalid_string');
    }

    #[Test]
    public function validateModelIdOrFilter_rejects_array_without_filter_key(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('model_id_or_filter must be an integer, true, or an object with a filter key');

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
            'status' => 'Running',
            'workflow_run_id' => 10,
        ];

        $filter2 = [
            'workflow_run_id' => 10,
            'status' => 'Running',
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
            'z' => 'last',
            'a' => 'first',
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
        $index = Cache::get($indexKey, []);

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
        $index = Cache::get($indexKey, []);

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
        $index = Cache::get($indexKey, []);

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
}
