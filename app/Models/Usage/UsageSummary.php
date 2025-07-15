<?php

namespace App\Models\Usage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UsageSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'object_type',
        'object_id',
        'object_id_int',
        'count',
        'run_time_ms',
        'input_tokens',
        'output_tokens',
        'input_cost',
        'output_cost',
        'total_cost',
        'request_count',
        'data_volume',
    ];

    protected $casts = [
        'count' => 'integer',
        'run_time_ms' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'input_cost' => 'float',
        'output_cost' => 'float',
        'total_cost' => 'float',
        'request_count' => 'integer',
        'data_volume' => 'integer',
    ];

    public function object(): MorphTo
    {
        return $this->morphTo('object', 'object_type', 'object_id');
    }

    /**
     * Ensure object_id is always stored as string
     */
    public function setObjectIdAttribute($value)
    {
        $this->attributes['object_id'] = (string) $value;
    }

    /**
     * Scope to handle string comparison for object_id
     */
    public function scopeWhereObjectId($query, $id)
    {
        return $query->where('object_id', (string) $id);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class, 'object_id_int', 'object_id_int')
            ->where('usage_events.object_type', $this->object_type);
    }

    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }

    public function updateFromEvents(): void
    {
        $summary = $this->usageEvents()
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

        if ($summary) {
            $this->update([
                'count' => $summary->count,
                'run_time_ms' => $summary->run_time_ms,
                'input_tokens' => $summary->input_tokens,
                'output_tokens' => $summary->output_tokens,
                'input_cost' => $summary->input_cost,
                'output_cost' => $summary->output_cost,
                'total_cost' => $summary->input_cost + $summary->output_cost,
                'request_count' => $summary->request_count,
                'data_volume' => $summary->data_volume,
            ]);
        }
    }
}
