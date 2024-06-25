<?php

namespace App\Models\Agent;

use App\Models\Team\Team;
use App\Repositories\AgentRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function runs()
    {
        return $this->hasMany(ThreadRun::class);
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
        return $this->runs()->where('status', ThreadRun::STATUS_RUNNING)->exists();
    }

    public function getTotalInputTokens()
    {
        if (isset($this->usage['input_tokens'])) {
            return $this->usage['input_tokens'];
        }

        return $this->usage['input_tokens'] = $this->runs()->sum('input_tokens');
    }

    public function getTotalOutputTokens()
    {
        if (isset($this->usage['output_tokens'])) {
            return $this->usage['output_tokens'];
        }

        return $this->usage['output_tokens'] = $this->runs()->sum('output_tokens');
    }

    public function getTotalCost()
    {
        if (isset($this->usage['cost'])) {
            return $this->usage['cost'];
        }

        $inputTokens  = $this->getTotalInputTokens();
        $outputTokens = $this->getTotalOutputTokens();

        $cost = app(AgentRepository::class)->calcTotalCost($this->agent, $inputTokens, $outputTokens);

        return $this->usage['cost'] = $cost;
    }

    public function __toString()
    {
        return "<Thread ($this->id) $this->name>";
    }
}
