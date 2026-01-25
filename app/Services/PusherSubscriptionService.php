<?php

namespace App\Services;

use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Exceptions\ValidationError;

class PusherSubscriptionService
{
    use HasDebugLogging;

    private const TTL = 300; // 5 minutes in seconds

    /**
     * Subscribe a user to model updates via Pusher
     *
     * @return array Subscription metadata including ID, resource_type, model_id_or_filter, events, expires_at, cache_key
     */
    public function subscribe(
        string $resourceType,
        int|string|bool|array $modelIdOrFilter,
        int $teamId,
        int $userId,
        array $events,
        string $subscriptionId
    ): array {
        self::logDebug('Starting subscription', [
            'subscription_id'    => $subscriptionId,
            'resource_type'      => $resourceType,
            'model_id_or_filter' => $modelIdOrFilter,
            'events'             => $events,
            'team_id'            => $teamId,
            'user_id'            => $userId,
        ]);

        $this->validateModelIdOrFilter($modelIdOrFilter);

        $cacheKey = $this->buildCacheKey($resourceType, $teamId, $modelIdOrFilter);

        self::logDebug('Generated cache key', [
            'subscription_id' => $subscriptionId,
            'cache_key'       => $cacheKey,
        ]);

        // Get current subscribers or initialize empty array
        $subscribers = Cache::get($cacheKey, []);

        $isNewSubscription = !in_array($userId, $subscribers);

        // Add user ID to subscribers if not already present
        if ($isNewSubscription) {
            $subscribers[] = $userId;
        }

        // Calculate expiration timestamp
        $expiresAt = now()->addSeconds(self::TTL);

        self::logDebug('Subscription metadata', [
            'subscription_id'       => $subscriptionId,
            'is_new_subscription'   => $isNewSubscription,
            'total_subscribers'     => count($subscribers),
            'expires_at'            => $expiresAt->toIso8601String(),
            'ttl_seconds'           => self::TTL,
        ]);

        // Store subscribers with TTL
        Cache::put($cacheKey, $subscribers, self::TTL);

        // Maintain an index of all subscription keys for non-Redis cache drivers
        $this->addToSubscriptionIndex($cacheKey);

        // For filter-based subscriptions, store the filter definition and maintain filter index
        if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
            $hash = $this->hashFilter($modelIdOrFilter['filter']);

            $definitionKey = $cacheKey . ':definition';
            Cache::put($definitionKey, $modelIdOrFilter['filter'], self::TTL);
            $this->addToSubscriptionIndex($definitionKey);

            // Add to filter index
            $filterIndexKey = "subscribe:{$resourceType}:{$teamId}:filters";
            $filterHashes   = Cache::get($filterIndexKey, []);
            if (!in_array($hash, $filterHashes)) {
                $filterHashes[] = $hash;
                Cache::put($filterIndexKey, $filterHashes, self::TTL);
            }
        }

        // Store subscription metadata by ID for keepalive lookups
        $subscriptionMetadata = [
            'id'                 => $subscriptionId,
            'resource_type'      => $resourceType,
            'model_id_or_filter' => $modelIdOrFilter,
            'events'             => $events,
            'team_id'            => $teamId,
            'user_id'            => $userId,
            'cache_key'          => $cacheKey,
            'expires_at'         => $expiresAt->toIso8601String(),
        ];

        Cache::put("subscription:id:{$subscriptionId}", $subscriptionMetadata, self::TTL);

        self::logDebug("User subscribed to {$resourceType}", [
            'subscription_id' => $subscriptionId,
            'cache_key'       => $cacheKey,
            'user_id'         => $userId,
            'team_id'         => $teamId,
        ]);

        return [
            'success'      => true,
            'subscription' => [
                'id'                 => $subscriptionId,
                'resource_type'      => $resourceType,
                'model_id_or_filter' => $modelIdOrFilter,
                'events'             => $events,
                'expires_at'         => $expiresAt->toIso8601String(),
                'cache_key'          => $cacheKey,
            ],
        ];
    }

    /**
     * Unsubscribe a user from model updates
     */
    public function unsubscribe(string $resourceType, int|string|bool|array $modelIdOrFilter, int $teamId, int $userId): void
    {
        self::logDebug('Starting unsubscribe', [
            'resource_type'      => $resourceType,
            'model_id_or_filter' => $modelIdOrFilter,
            'team_id'            => $teamId,
            'user_id'            => $userId,
        ]);

        $this->validateModelIdOrFilter($modelIdOrFilter);

        $cacheKey = $this->buildCacheKey($resourceType, $teamId, $modelIdOrFilter);

        // Get current subscribers
        $subscribers   = Cache::get($cacheKey, []);
        $wasSubscribed = in_array($userId, $subscribers);

        self::logDebug('Subscription state', [
            'cache_key'         => $cacheKey,
            'user_id'           => $userId,
            'was_subscribed'    => $wasSubscribed,
            'total_subscribers' => count($subscribers),
        ]);

        if (!$wasSubscribed) {
            self::logDebug('User was not subscribed', [
                'cache_key' => $cacheKey,
                'user_id'   => $userId,
            ]);
        }

        // Remove user ID from subscribers
        $subscribers = array_values(array_filter($subscribers, fn($id) => $id !== $userId));

        if (empty($subscribers)) {
            self::logDebug('No remaining subscribers, removing cache keys', [
                'cache_key' => $cacheKey,
                'user_id'   => $userId,
            ]);

            // Delete cache key if no subscribers remain
            Cache::forget($cacheKey);
            $this->removeFromSubscriptionIndex($cacheKey);

            // Also delete filter definition and remove from filter index
            if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
                $hash = $this->hashFilter($modelIdOrFilter['filter']);

                $definitionKey = $cacheKey . ':definition';
                Cache::forget($definitionKey);
                $this->removeFromSubscriptionIndex($definitionKey);

                // Remove from filter index
                $filterIndexKey = "subscribe:{$resourceType}:{$teamId}:filters";
                $filterHashes   = Cache::get($filterIndexKey, []);
                $filterHashes   = array_values(array_filter($filterHashes, fn($h) => $h !== $hash));

                if (empty($filterHashes)) {
                    Cache::forget($filterIndexKey);
                } else {
                    Cache::put($filterIndexKey, $filterHashes, self::TTL);
                }

                self::logDebug('Filter definition removed', [
                    'definition_key' => $definitionKey,
                    'filter_index'   => $filterIndexKey,
                ]);
            }
        } else {
            self::logDebug('Updating subscribers list', [
                'cache_key'             => $cacheKey,
                'remaining_subscribers' => count($subscribers),
            ]);

            // Update with remaining subscribers
            Cache::put($cacheKey, $subscribers, self::TTL);
        }

        self::logDebug("User unsubscribed from {$resourceType}", [
            'cache_key'      => $cacheKey,
            'user_id'        => $userId,
            'team_id'        => $teamId,
            'was_subscribed' => $wasSubscribed,
        ]);
    }

    /**
     * Keep subscriptions alive by refreshing TTL using subscription IDs
     *
     * @param  array  $subscriptionIds  Array of subscription IDs to refresh
     * @param  int  $userId  User ID to verify ownership
     * @return array Results array with success/error status for each subscription ID
     */
    public function keepaliveByIds(array $subscriptionIds, int $userId): array
    {
        self::logDebug('Starting keepalive by IDs', [
            'user_id'              => $userId,
            'total_subscriptions'  => count($subscriptionIds),
            'subscription_ids'     => $subscriptionIds,
        ]);

        $results = [];

        foreach ($subscriptionIds as $subscriptionId) {
            $metadataKey = "subscription:id:{$subscriptionId}";
            $metadata    = Cache::get($metadataKey);

            if (!$metadata) {
                self::logDebug('Subscription not found', [
                    'subscription_id' => $subscriptionId,
                    'user_id'         => $userId,
                    'metadata_key'    => $metadataKey,
                ]);

                $results[$subscriptionId] = [
                    'success' => false,
                    'error'   => 'Subscription not found or expired',
                ];

                continue;
            }

            self::logDebug('Processing keepalive for subscription', [
                'subscription_id' => $subscriptionId,
                'user_id'         => $userId,
                'found'           => true,
                'resource_type'   => $metadata['resource_type'] ?? null,
                'cache_key'       => $metadata['cache_key']     ?? null,
            ]);

            // Verify this subscription belongs to this user
            if ($metadata['user_id'] !== $userId) {
                self::logDebug('Ownership verification failed', [
                    'subscription_id' => $subscriptionId,
                    'user_id'         => $userId,
                    'owner_user_id'   => $metadata['user_id'],
                ]);

                $results[$subscriptionId] = [
                    'success' => false,
                    'error'   => 'Unauthorized',
                ];

                continue;
            }

            // Check if user is still in the subscribers list
            $cacheKey    = $metadata['cache_key'];
            $subscribers = Cache::get($cacheKey, []);

            if (!in_array($userId, $subscribers)) {
                self::logDebug('User not in subscribers list', [
                    'subscription_id' => $subscriptionId,
                    'user_id'         => $userId,
                    'cache_key'       => $cacheKey,
                    'subscribers'     => $subscribers,
                ]);

                $results[$subscriptionId] = [
                    'success' => false,
                    'error'   => 'User not subscribed',
                ];

                continue;
            }

            // Refresh TTL
            $expiresAt = now()->addSeconds(self::TTL);
            Cache::put($cacheKey, $subscribers, self::TTL);
            Cache::put($metadataKey, array_merge($metadata, [
                'expires_at' => $expiresAt->toIso8601String(),
            ]), self::TTL);

            self::logDebug('TTL refreshed successfully', [
                'subscription_id' => $subscriptionId,
                'user_id'         => $userId,
                'cache_key'       => $cacheKey,
                'expires_at'      => $expiresAt->toIso8601String(),
                'ttl_seconds'     => self::TTL,
            ]);

            // Also refresh filter definition and filter index TTL if exists
            $modelIdOrFilter = $metadata['model_id_or_filter'];
            if (is_array($modelIdOrFilter) && isset($modelIdOrFilter['filter'])) {
                $definitionKey = $cacheKey . ':definition';
                $filter        = Cache::get($definitionKey);
                if ($filter !== null) {
                    Cache::put($definitionKey, $filter, self::TTL);

                    // Refresh filter index TTL
                    $resourceType   = $metadata['resource_type'];
                    $teamId         = $metadata['team_id'];
                    $filterIndexKey = "subscribe:{$resourceType}:{$teamId}:filters";
                    $filterHashes   = Cache::get($filterIndexKey, []);
                    if (!empty($filterHashes)) {
                        Cache::put($filterIndexKey, $filterHashes, self::TTL);
                    }

                    self::logDebug('Filter definition TTL refreshed', [
                        'subscription_id' => $subscriptionId,
                        'definition_key'  => $definitionKey,
                        'filter_index'    => $filterIndexKey,
                    ]);
                }
            }

            $results[$subscriptionId] = [
                'success'    => true,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failureCount = count($subscriptionIds) - $successCount;

        self::logDebug('Keepalive by IDs completed', [
            'user_id'              => $userId,
            'total_subscriptions'  => count($subscriptionIds),
            'successful_refreshes' => $successCount,
            'failed_refreshes'     => $failureCount,
        ]);

        return $results;
    }

    /**
     * Validate model_id_or_filter parameter
     */
    public function validateModelIdOrFilter(mixed $value): void
    {
        // Reject any empty value: null, '', [], false, 0
        if (empty($value)) {
            throw new ValidationError('model_id_or_filter cannot be empty');
        }

        // Must be integer, string, boolean true, or array with 'filter' key
        $isValid = is_int($value)
            || is_string($value)
            || $value === true
            || (is_array($value) && isset($value['filter']) && !empty($value['filter']));

        if (!$isValid) {
            throw new ValidationError('model_id_or_filter must be an integer, a string, true, or an object with a filter key');
        }

        // Validate string IDs are reasonable (not empty after trim, reasonable length)
        if (is_string($value)) {
            $trimmed = trim($value);
            if (empty($trimmed)) {
                throw new ValidationError('model_id_or_filter string cannot be empty or whitespace only');
            }
            if (strlen($trimmed) > 255) {
                throw new ValidationError('model_id_or_filter string cannot exceed 255 characters');
            }
        }
    }

    /**
     * Build cache key for subscription
     */
    public function buildCacheKey(string $resourceType, int $teamId, int|string|bool|array $modelIdOrFilter): string
    {
        $prefix = "subscribe:{$resourceType}:{$teamId}";

        if ($modelIdOrFilter === true) {
            return "{$prefix}:all";
        }

        // Handle both numeric and string IDs
        if (is_int($modelIdOrFilter) || is_string($modelIdOrFilter)) {
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
