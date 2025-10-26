<?php

namespace App\Services;

use App\Traits\HasDebugLogging;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Exceptions\ValidationError;

class PusherSubscriptionService
{
    use HasDebugLogging;

    private const TTL = 300; // 5 minutes in seconds

    /**
     * Subscribe a user to model updates via Pusher
     */
    public function subscribe(string $resourceType, $modelIdOrFilter, int $teamId, int $userId): void
    {
        $this->validateModelIdOrFilter($modelIdOrFilter);

        $cacheKey = $this->buildCacheKey($resourceType, $teamId, $modelIdOrFilter);

        // Get current subscribers or initialize empty array
        $subscribers = Cache::get($cacheKey, []);

        // Add user ID to subscribers if not already present
        if (!in_array($userId, $subscribers)) {
            $subscribers[] = $userId;
        }

        // Store subscribers with TTL
        Cache::put($cacheKey, $subscribers, self::TTL);

        // Maintain an index of all subscription keys for non-Redis cache drivers
        $this->addToSubscriptionIndex($cacheKey);

        // For filter-based subscriptions, store the filter definition
        if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
            $definitionKey = $cacheKey . ':definition';
            Cache::put($definitionKey, $modelIdOrFilter['filter'], self::TTL);
            $this->addToSubscriptionIndex($definitionKey);
        }

        self::log("User subscribed to {$resourceType}", [
            'cache_key' => $cacheKey,
            'user_id'   => $userId,
            'team_id'   => $teamId,
        ]);
    }

    /**
     * Unsubscribe a user from model updates
     */
    public function unsubscribe(string $resourceType, $modelIdOrFilter, int $teamId, int $userId): void
    {
        $this->validateModelIdOrFilter($modelIdOrFilter);

        $cacheKey = $this->buildCacheKey($resourceType, $teamId, $modelIdOrFilter);

        // Get current subscribers
        $subscribers = Cache::get($cacheKey, []);

        // Remove user ID from subscribers
        $subscribers = array_values(array_filter($subscribers, fn($id) => $id !== $userId));

        if (empty($subscribers)) {
            // Delete cache key if no subscribers remain
            Cache::forget($cacheKey);
            $this->removeFromSubscriptionIndex($cacheKey);

            // Also delete filter definition if exists
            if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
                $definitionKey = $cacheKey . ':definition';
                Cache::forget($definitionKey);
                $this->removeFromSubscriptionIndex($definitionKey);
            }
        } else {
            // Update with remaining subscribers
            Cache::put($cacheKey, $subscribers, self::TTL);
        }

        self::log("User unsubscribed from {$resourceType}", [
            'cache_key' => $cacheKey,
            'user_id'   => $userId,
            'team_id'   => $teamId,
        ]);
    }

    /**
     * Keep subscriptions alive by refreshing TTL
     * Returns the count of refreshed subscriptions
     */
    public function keepalive(array $subscriptions, int $teamId, int $userId): int
    {
        $refreshedCount = 0;

        foreach ($subscriptions as $subscription) {
            $resourceType    = $subscription['resource_type'];
            $modelIdOrFilter = $subscription['model_id_or_filter'];

            // Validate model_id_or_filter
            $this->validateModelIdOrFilter($modelIdOrFilter);

            $cacheKey = $this->buildCacheKey($resourceType, $teamId, $modelIdOrFilter);

            // Check if user is in the subscribers list
            $subscribers = Cache::get($cacheKey, []);

            if (in_array($userId, $subscribers)) {
                // Refresh TTL
                Cache::put($cacheKey, $subscribers, self::TTL);
                $refreshedCount++;

                // Also refresh filter definition TTL if exists
                if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
                    $definitionKey = $cacheKey . ':definition';
                    $filter        = Cache::get($definitionKey);
                    if ($filter !== null) {
                        Cache::put($definitionKey, $filter, self::TTL);
                    }
                }
            }
        }

        self::log("Keepalive refreshed {$refreshedCount} subscriptions", [
            'user_id'             => $userId,
            'team_id'             => $teamId,
            'total_subscriptions' => count($subscriptions),
        ]);

        return $refreshedCount;
    }

    /**
     * Validate model_id_or_filter parameter
     */
    public function validateModelIdOrFilter($value): void
    {
        // Reject any empty value: null, '', [], false, 0
        if (empty($value)) {
            throw new ValidationError('model_id_or_filter cannot be empty');
        }

        // Must be integer, boolean true, or array with 'filter' key
        $isValid = is_int($value)
            || $value === true
            || (is_array($value) && isset($value['filter']) && !empty($value['filter']));

        if (!$isValid) {
            throw new ValidationError('model_id_or_filter must be an integer, true, or an object with a filter key');
        }
    }

    /**
     * Build cache key for subscription
     */
    public function buildCacheKey(string $resourceType, int $teamId, $modelIdOrFilter): string
    {
        $prefix = "subscribe:{$resourceType}:{$teamId}";

        if ($modelIdOrFilter === true) {
            return "{$prefix}:all";
        }

        if (is_int($modelIdOrFilter)) {
            return "{$prefix}:id:{$modelIdOrFilter}";
        }

        if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
            $hash = $this->hashFilter($modelIdOrFilter['filter']);

            return "{$prefix}:filter:{$hash}";
        }

        throw new ValidationError('Invalid model_id_or_filter format');
    }

    /**
     * Generate MD5 hash of filter object with recursive key sorting
     */
    public function hashFilter(array $filter): string
    {
        $sorted = $this->sortArrayRecursively($filter);
        $json   = json_encode($sorted, JSON_UNESCAPED_SLASHES);

        return md5($json);
    }

    /**
     * Recursively sort array keys for consistent hashing
     */
    public function sortArrayRecursively(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortArrayRecursively($value);
            }
        }

        return $array;
    }

    /**
     * Add a cache key to the subscription index (for non-Redis drivers)
     */
    public function addToSubscriptionIndex(string $cacheKey): void
    {
        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        if (!in_array($cacheKey, $index)) {
            $index[] = $cacheKey;
            // Store index with longer TTL (1 hour) since it's just metadata
            Cache::put($indexKey, $index, 3600);
        }
    }

    /**
     * Remove a cache key from the subscription index
     */
    public function removeFromSubscriptionIndex(string $cacheKey): void
    {
        $indexKey = 'subscribe:_index';
        $index    = Cache::get($indexKey, []);

        $index = array_values(array_filter($index, fn($key) => $key !== $cacheKey));

        if (empty($index)) {
            Cache::forget($indexKey);
        } else {
            Cache::put($indexKey, $index, 3600);
        }
    }
}
