<?php

namespace App\Models\Traits;

use App\Models\Usage\UsageEvent;
use App\Models\Usage\UsageSummary;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasUsageTracking
{
    public function usageEvents(): MorphMany
    {
        return $this->morphMany(UsageEvent::class, 'object', 'object_type', 'object_id_int');
    }

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object', 'object_type', 'object_id_int');
    }

    public function refreshUsageSummary(): void
    {
        $summary = $this->usageSummary ?: $this->usageSummary()->create([
            'object_type'   => $this->getMorphClass(),
            'object_id'     => $this->id,
            'object_id_int' => $this->id,
        ]);

        $summary->updateFromEvents();
    }

    public function aggregateChildUsage(string $childRelation): void
    {
        if (!method_exists($this, $childRelation)) {
            return;
        }

        $childModel   = $this->$childRelation()->getRelated();
        $childTable   = $childModel->getTable();
        $childKeyName = $childModel->getKeyName();

        $childSummaries = $this->$childRelation()
            ->join('usage_summaries', function ($join) use ($childTable, $childKeyName, $childModel) {
                $join->on('usage_summaries.object_id_int', '=', "{$childTable}.{$childKeyName}")
                    ->where('usage_summaries.object_type', '=', $childModel->getMorphClass());
            })
            ->selectRaw('
                COUNT(DISTINCT usage_summaries.id) as count,
                COALESCE(SUM(usage_summaries.run_time_ms), 0) as run_time_ms,
                COALESCE(SUM(usage_summaries.input_tokens), 0) as input_tokens,
                COALESCE(SUM(usage_summaries.output_tokens), 0) as output_tokens,
                COALESCE(SUM(usage_summaries.input_cost), 0) as input_cost,
                COALESCE(SUM(usage_summaries.output_cost), 0) as output_cost,
                COALESCE(SUM(usage_summaries.total_cost), 0) as total_cost,
                COALESCE(SUM(usage_summaries.request_count), 0) as request_count,
                COALESCE(SUM(usage_summaries.data_volume), 0) as data_volume
            ')
            ->first();

        if ($childSummaries && $childSummaries->count > 0) {
            $summary = $this->usageSummary ?: $this->usageSummary()->create([
                'object_type'   => $this->getMorphClass(),
                'object_id'     => $this->id,
                'object_id_int' => $this->id,
            ]);

            $summary->update([
                'count'         => $childSummaries->count,
                'run_time_ms'   => $childSummaries->run_time_ms,
                'input_tokens'  => $childSummaries->input_tokens,
                'output_tokens' => $childSummaries->output_tokens,
                'input_cost'    => $childSummaries->input_cost,
                'output_cost'   => $childSummaries->output_cost,
                'total_cost'    => $childSummaries->total_cost,
                'request_count' => $childSummaries->request_count,
                'data_volume'   => $childSummaries->data_volume,
            ]);
        }
    }

    public function subscribedUsageEvents(): MorphToMany
    {
        return $this->morphToMany(
            UsageEvent::class,
            'subscriber',
            'usage_event_subscribers',
            'subscriber_id',
            'usage_event_id'
        )->withPivot('subscribed_at')->withTimestamps();
    }

    public function subscribeToUsageEvent(UsageEvent $usageEvent): void
    {
        if (!$this->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists()) {
            $this->subscribedUsageEvents()->attach($usageEvent->id, [
                'subscriber_type'   => $this->getMorphClass(),
                'subscriber_id'     => (string)$this->id,
                'subscriber_id_int' => $this->id,
                'subscribed_at'     => now(),
            ]);
        }
    }

    public function unsubscribeFromUsageEvent(UsageEvent $usageEvent): void
    {
        $this->subscribedUsageEvents()->detach($usageEvent->id);
    }

    public function refreshUsageSummaryFromSubscribedEvents(): void
    {
        $aggregatedData = UsageEvent::whereIn('usage_events.id', $this->subscribedUsageEvents()->pluck('usage_events.id'))
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(run_time_ms), 0) as run_time_ms,
                COALESCE(SUM(input_tokens), 0) as input_tokens,
                COALESCE(SUM(output_tokens), 0) as output_tokens,
                COALESCE(SUM(input_cost), 0) as input_cost,
                COALESCE(SUM(output_cost), 0) as output_cost,
                COALESCE(SUM(request_count), 0) as request_count,
                COALESCE(SUM(data_volume), 0) as data_volume
            ')
            ->first();

        if ($aggregatedData && $aggregatedData->count > 0) {
            $summary = $this->usageSummary ?: $this->usageSummary()->create([
                'object_type'   => $this->getMorphClass(),
                'object_id'     => $this->id,
                'object_id_int' => $this->id,
            ]);

            $summary->update([
                'count'         => $aggregatedData->count,
                'run_time_ms'   => $aggregatedData->run_time_ms,
                'input_tokens'  => $aggregatedData->input_tokens,
                'output_tokens' => $aggregatedData->output_tokens,
                'input_cost'    => $aggregatedData->input_cost,
                'output_cost'   => $aggregatedData->output_cost,
                'total_cost'    => $aggregatedData->input_cost + $aggregatedData->output_cost,
                'request_count' => $aggregatedData->request_count,
                'data_volume'   => $aggregatedData->data_volume,
            ]);
        } else {
            // If no subscribed events, delete the summary
            if ($this->usageSummary) {
                $this->usageSummary->delete();
                $this->unsetRelation('usageSummary');
            }
        }
    }
}
