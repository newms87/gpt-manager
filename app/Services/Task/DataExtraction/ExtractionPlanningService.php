<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskDefinition;
use App\Traits\HasDebugLogging;

/**
 * Service for caching extraction plans.
 *
 * Provides methods to check, store, and compute cache keys for extraction plans.
 */
class ExtractionPlanningService
{
    use HasDebugLogging;

    /**
     * Check if cached plan exists and is valid.
     */
    public function getCachedPlan(TaskDefinition $taskDefinition): ?array
    {
        $meta = $taskDefinition->meta ?? [];

        if (!isset($meta['extraction_plan'])) {
            static::logDebug('No cached extraction plan found');

            return null;
        }

        $cachedPlanKey   = $meta['extraction_plan_cache_key'] ?? null;
        $currentCacheKey = $this->computeCacheKey($taskDefinition);

        if ($cachedPlanKey !== $currentCacheKey) {
            static::logDebug('Cached extraction plan is invalid (cache key mismatch)', [
                'cached_key'  => $cachedPlanKey,
                'current_key' => $currentCacheKey,
            ]);

            return null;
        }

        static::logDebug('Valid cached extraction plan found');

        return $meta['extraction_plan'];
    }

    /**
     * Compute cache key from inputs.
     */
    public function computeCacheKey(TaskDefinition $taskDefinition): string
    {
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$schemaDefinition) {
            return '';
        }

        $inputs = [
            'schema'              => $schemaDefinition->schema,
            'user_planning_hints' => $taskDefinition->task_runner_config['user_planning_hints']    ?? null,
            'global_search_mode'  => $taskDefinition->task_runner_config['global_search_mode']     ?? 'intelligent',
            'group_max_points'    => $taskDefinition->task_runner_config['group_max_points']       ?? 10,
        ];

        return hash('sha256', json_encode($inputs));
    }

    /**
     * Store plan in TaskDefinition.meta.
     */
    public function cachePlan(TaskDefinition $taskDefinition, array $plan): void
    {
        $meta = $taskDefinition->meta ?? [];

        $meta['extraction_plan']              = $plan;
        $meta['extraction_plan_cache_key']    = $this->computeCacheKey($taskDefinition);
        $meta['extraction_plan_generated_at'] = now()->toISOString();

        $taskDefinition->meta = $meta;
        $taskDefinition->save();

        static::logDebug('Cached extraction plan in TaskDefinition', [
            'task_definition_id' => $taskDefinition->id,
            'cache_key'          => $meta['extraction_plan_cache_key'],
        ]);
    }
}
