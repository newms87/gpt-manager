<?php

namespace App\Models\Usage;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsageEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'object_type',
        'object_id',
        'object_id_int',
        'event_type',
        'api_name',
        'run_time_ms',
        'input_tokens',
        'output_tokens',
        'input_cost',
        'output_cost',
        'request_count',
        'data_volume',
        'metadata',
    ];

    protected $casts = [
        'run_time_ms'   => 'integer',
        'input_tokens'  => 'integer',
        'output_tokens' => 'integer',
        'input_cost'    => 'float',
        'output_cost'   => 'float',
        'request_count' => 'integer',
        'data_volume'   => 'integer',
        'metadata'      => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function object(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ensure object_id is always stored as string
     */
    public function setObjectIdAttribute($value)
    {
        $this->attributes['object_id'] = (string)$value;
    }

    public function getTotalCostAttribute(): float
    {
        return ($this->input_cost ?? 0) + ($this->output_cost ?? 0);
    }

    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }

    public function usageEventSubscribers(): HasMany
    {
        return $this->hasMany(UsageEventSubscriber::class);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(UsageEventSubscriber::class);
    }
}
