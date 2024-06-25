<?php

namespace App\Models\Agent;

use App\Models\Team\Team;
use App\Repositories\AgentRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\CountableTrait;

class Thread extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, CountableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $usage = [];

    public array $relatedCounters = [
        Agent::class => 'threads_count',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function runs(): HasMany|ThreadRun
    {
        return $this->hasMany(ThreadRun::class);
    }

    public function currentRun(): HasOne|ThreadRun
    {
        return $this->hasOne(ThreadRun::class)->where('status', ThreadRun::STATUS_RUNNING);
    }

    public function lastRun(): HasOne|ThreadRun
    {
        return $this->hasOne(ThreadRun::class)->latest();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function agent()
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
            $count        = $this->runs()->count();
            $inputTokens  = $this->runs()->sum('input_tokens') ?? 0;
            $outputTokens = $this->runs()->sum('output_tokens') ?? 0;

            $cost = app(AgentRepository::class)->calcTotalCost($this->agent, $inputTokens, $outputTokens);

            $this->usage = [
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost'          => $cost,
                'count'         => $count,
            ];
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
        return $this->getUsage()['cost'];
    }

    public function __toString()
    {
        return "<Thread ($this->id) $this->name>";
    }
}
