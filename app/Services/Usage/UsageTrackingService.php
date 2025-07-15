<?php

namespace App\Services\Usage;

use App\Models\Usage\UsageEvent;
use App\Models\Usage\UsageSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UsageTrackingService
{
    public function __construct(
        private CostCalculationService $costCalculation,
        private PricingService $pricing
    ) {}
    public function recordUsage(
        Model $object,
        string $eventType,
        string $apiName,
        array $usageData = [],
        ?User $user = null
    ): UsageEvent {
        $user = $user ?? auth()->user();
        $team = team();

        $usageEvent = UsageEvent::create([
            'team_id' => $team?->id,
            'user_id' => $user?->id,
            'object_type' => $object->getMorphClass(),
            'object_id' => $object->id,
            'object_id_int' => $object->id,
            'event_type' => $eventType,
            'api_name' => $apiName,
            'run_time_ms' => $usageData['run_time_ms'] ?? 0,
            'input_tokens' => $usageData['input_tokens'] ?? 0,
            'output_tokens' => $usageData['output_tokens'] ?? 0,
            'input_cost' => $usageData['input_cost'] ?? 0,
            'output_cost' => $usageData['output_cost'] ?? 0,
            'request_count' => $usageData['request_count'] ?? 1,
            'data_volume' => $usageData['data_volume'] ?? 0,
            'metadata' => $usageData['metadata'] ?? null,
        ]);

        $this->updateUsageSummary($object);

        return $usageEvent;
    }

    public function calculateCosts(
        string $apiName,
        string $modelName,
        array $usage
    ): array {
        return $this->costCalculation->calculateAiCosts($apiName, $modelName, $usage);
    }

    public function recordAiUsage(
        Model $object,
        string $apiName,
        string $modelName,
        array $usage,
        ?int $runTimeMs = null,
        ?User $user = null
    ): UsageEvent {
        $costs = $this->calculateCosts($apiName, $modelName, $usage);

        return $this->recordUsage($object, 'ai_completion', $apiName, [
            'run_time_ms' => $runTimeMs,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'input_cost' => $costs['input_cost'],
            'output_cost' => $costs['output_cost'],
            'request_count' => 1,
            'metadata' => [
                'model' => $modelName,
                'cached_input_tokens' => $usage['cached_input_tokens'] ?? null,
                'api_response' => $usage['api_response'] ?? null,
            ],
        ], $user);
    }

    public function recordApiUsage(
        Model $object,
        string $apiName,
        string $eventType,
        array $usage = [],
        ?int $runTimeMs = null,
        ?User $user = null
    ): UsageEvent {
        $costs = $this->costCalculation->calculateApiCosts($apiName, $usage);

        return $this->recordUsage($object, $eventType, $apiName, [
            'run_time_ms' => $runTimeMs,
            'input_cost' => $costs['input_cost'],
            'output_cost' => $costs['output_cost'],
            'request_count' => $usage['request_count'] ?? 1,
            'data_volume' => $usage['data_volume'] ?? null,
            'metadata' => $usage['metadata'] ?? null,
        ], $user);
    }


    protected function updateUsageSummary(Model $object): void
    {
        $summary = UsageSummary::where('object_type', $object->getMorphClass())
            ->where('object_id_int', $object->id)
            ->first();
            
        if (!$summary) {
            $summary = UsageSummary::create([
                'object_type' => $object->getMorphClass(),
                'object_id' => $object->id,
                'object_id_int' => $object->id,
            ]);
        }

        $summary->updateFromEvents();
    }

    public function aggregateUsageForParent(Model $parent, string $childRelation): void
    {
        if (method_exists($parent, $childRelation) && method_exists($parent, 'aggregateChildUsage')) {
            $parent->aggregateChildUsage($childRelation);
        }
    }
}