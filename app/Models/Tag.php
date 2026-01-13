<?php

namespace App\Models;

use App\Models\Team\Team;
use App\Models\Template\TemplateDefinition;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class Tag extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'type',
    ];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function workflowInputs(): MorphToMany|WorkflowInput
    {
        return $this->morphedByMany(WorkflowInput::class, 'taggable', 'taggables');
    }

    public function templateDefinitions(): MorphToMany|TemplateDefinition
    {
        return $this->morphedByMany(TemplateDefinition::class, 'taggable', 'taggables');
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        if ($type === null) {
            return $query->whereNull('type');
        }

        return $query->where('type', $type);
    }

    public function scopeWithName(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'team_id' => ['required', 'exists:teams,id'],
            'name'    => ['required', 'string', 'max:255'],
            'type'    => ['nullable', 'string', 'max:255'],
        ])->validate();

        return $this;
    }
}
