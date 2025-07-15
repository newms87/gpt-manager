<?php

namespace App\Models\Traits;

use App\Models\Usage\UsageEvent;
use App\Models\Usage\UsageSummary;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    public function getUsageAttribute(): ?array
    {
        if (!$this->usageSummary) {
            return null;
        }

        return [
            'count' => $this->usageSummary->count ?? 0,
            'run_time_ms' => $this->usageSummary->run_time_ms ?? 0,
            'input_tokens' => $this->usageSummary->input_tokens ?? 0,
            'output_tokens' => $this->usageSummary->output_tokens ?? 0,
            'total_tokens' => ($this->usageSummary->input_tokens ?? 0) + ($this->usageSummary->output_tokens ?? 0),
            'input_cost' => (float) ($this->usageSummary->input_cost ?? 0),
            'output_cost' => (float) ($this->usageSummary->output_cost ?? 0),
            'total_cost' => (float) ($this->usageSummary->total_cost ?? 0),
            'request_count' => $this->usageSummary->request_count ?? 0,
            'data_volume' => $this->usageSummary->data_volume ?? 0,
        ];
    }

    public function refreshUsageSummary(): void
    {
        $summary = $this->usageSummary ?: $this->usageSummary()->create([
            'object_type' => $this->getMorphClass(),
            'object_id' => $this->id,
            'object_id_int' => $this->id,
        ]);

        $summary->updateFromEvents();
    }

    public function aggregateChildUsage(string $childRelation): void
    {
        if (!method_exists($this, $childRelation)) {
            return;
        }

        $childModel = $this->$childRelation()->getRelated();
        $childTable = $childModel->getTable();
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
                'object_type' => $this->getMorphClass(),
                'object_id' => $this->id,
                'object_id_int' => $this->id,
            ]);

            $summary->update([
                'count' => $childSummaries->count,
                'run_time_ms' => $childSummaries->run_time_ms,
                'input_tokens' => $childSummaries->input_tokens,
                'output_tokens' => $childSummaries->output_tokens,
                'input_cost' => $childSummaries->input_cost,
                'output_cost' => $childSummaries->output_cost,
                'total_cost' => $childSummaries->total_cost,
                'request_count' => $childSummaries->request_count,
                'data_volume' => $childSummaries->data_volume,
            ]);
        }
    }
}