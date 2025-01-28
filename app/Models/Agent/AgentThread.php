<?php

namespace App\Models\Agent;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class AgentThread extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected array $usage = [];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function runs(): HasMany|AgentThreadRun
    {
        return $this->hasMany(AgentThreadRun::class);
    }

    public function currentRun(): HasOne|AgentThreadRun
    {
        return $this->hasOne(AgentThreadRun::class)->where('status', AgentThreadRun::STATUS_RUNNING);
    }

    public function lastRun(): HasOne|AgentThreadRun
    {
        return $this->hasOne(AgentThreadRun::class)->latest();
    }

    public function messages(): HasMany|AgentThreadMessage
    {
        return $this->hasMany(AgentThreadMessage::class);
    }

    public function sortedMessages(): HasMany|AgentThreadMessage
    {
        return $this->messages()->orderBy('id');
    }

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function isRunning(): bool
    {
        return $this->currentRun()->exists();
    }

    public function getUsage(): array
    {
        if (!$this->usage) {
            $this->usage = $this->runs()->withTrashed()->selectRaw(
                'count(*) as count,' .
                'ifnull(sum(ifnull(input_tokens, 0)), 0) as input_tokens,' .
                'ifnull(sum(ifnull(output_tokens, 0)), 0) as output_tokens,' .
                'ifnull(sum(ifnull(total_cost, 0)), 0) as total_cost'
            )
                ->first()
                ->toArray();
        }

        return $this->usage;
    }

    public function getTotalInputTokens()
    {
        return $this->getUsage()['input_tokens'];
    }

    public function getTotalOutputTokens()
    {
        return $this->getUsage()['output_tokens'];
    }

    public function getTotalCost()
    {
        return $this->getUsage()['total_cost'];
    }

    public function __toString()
    {
        return "<AgentThread ($this->id) $this->name>";
    }
}
