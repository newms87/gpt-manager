<?php

namespace App\Models\Agent;

use App\Events\AgentThreadUpdatedEvent;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class AgentThread extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, ActionModelTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected array $usage = [];

    protected array $can = [
        'view' => null,
        'edit' => null,
    ];

    public function can($type = null): bool|array
    {
        if ($this->can['view'] === null) {
            $this->can['view'] = true;
            $this->can['edit'] = true;

            $runs = $this->runs()->whereHas('responseSchema.resourcePackageImport')->with('responseSchema.resourcePackageImport.resourcePackage')->get();

            foreach($runs as $run) {
                if (!$run->responseSchema->canView()) {
                    $this->can['view'] = false;
                }

                if (!$run->responseSchema->canEdit()) {
                    $this->can['edit'] = false;
                }
            }
        }

        return $type ? $this->can[$type] : $this->can;
    }

    public function canView(): bool
    {
        return $this->can('view');
    }

    public function canEdit(): bool
    {
        return $this->can('edit');
    }

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

    public static function booted()
    {
        static::saved(function (AgentThread $agentThread) {
            if ($agentThread->wasChanged(['name', 'summary'])) {
                AgentThreadUpdatedEvent::dispatch($agentThread);
            }
        });
    }

    public function __toString()
    {
        return "<AgentThread ($this->id) $this->name>";
    }
}
