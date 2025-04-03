<?php

namespace App\Models\Prompt;

use App\Models\Agent\Agent;
use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Team\Team;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class PromptDirective extends Model implements AuditableContract, ResourcePackageableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait, ResourcePackageableTrait, HasRelationCountersTrait, SoftDeletes, KeywordSearchTrait;

    protected $fillable = [
        'name',
        'directive_text',
    ];

    protected array $keywordFields = [
        'name',
        'directive_text',
    ];

    public array $relationCounters = [
        Agent::class => ['agents' => 'agents_count'],
    ];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function agents(): BelongsToMany|Agent
    {
        return $this->belongsToMany(Agent::class, 'agent_prompt_directives');
    }

    public function agentPromptDirectives(): HasMany|AgentPromptDirective
    {
        return $this->hasMany(AgentPromptDirective::class);
    }

    public function taskDefinitionDirectives(): HasMany|TaskDefinitionDirective
    {
        return $this->hasMany(TaskDefinitionDirective::class);
    }

    public function delete(): bool
    {
        foreach($this->agentPromptDirectives as $agentPromptDirective) {
            $agentPromptDirective->delete();
        }

        foreach($this->taskDefinitionDirectives as $taskDefinitionDirective) {
            $taskDefinitionDirective->delete();
        }

        return parent::delete();
    }


    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('prompt_directives')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public static function booted(): void
    {
        static::creating(function (PromptDirective $agent) {
            $agent->team_id = $agent->team_id ?? team()->id;
        });
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'name'           => $this->name,
            'directive_text' => $this->directive_text,
        ]);
    }

    public function __toString(): string
    {
        return "<PromptDirective $this->name>";
    }
}
