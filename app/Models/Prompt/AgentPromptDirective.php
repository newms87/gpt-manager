<?php

namespace App\Models\Prompt;

use App\Models\Agent\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class AgentPromptDirective extends Model implements AuditableContract
{
    use AuditableTrait;

    const string
        SECTION_TOP = 'Top',
        SECTION_BOTTOM = 'Bottom';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function directive(): BelongsTo|PromptDirective
    {
        return $this->belongsTo(PromptDirective::class, 'prompt_directive_id');
    }

    public function __toString(): string
    {
        return "<AgentPromptDirective {$this->directive->name} position='$this->position' section='$this->section'>";
    }
}
