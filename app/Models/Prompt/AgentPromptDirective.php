<?php

namespace App\Models\Prompt;

use App\Models\Agent\Agent;
use App\Models\CanExportToJsonContract;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class AgentPromptDirective extends Model implements AuditableContract, CanExportToJsonContract
{
    use AuditableTrait, ActionModelTrait;

    const string
        SECTION_TOP = 'Top',
        SECTION_BOTTOM = 'Bottom';

    protected $fillable = [
        'prompt_directive_id',
        'section',
        'position',
    ];

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function directive(): BelongsTo|PromptDirective
    {
        return $this->belongsTo(PromptDirective::class, 'prompt_directive_id');
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'agent_id'            => $service->registerRelatedModel($this->agent),
            'prompt_directive_id' => $service->registerRelatedModel($this->directive),
            'section'             => $this->section,
            'position'            => $this->position,
        ]);
    }

    public function __toString(): string
    {
        return "<AgentPromptDirective {$this->directive->name} position='$this->position' section='$this->section'>";
    }
}
