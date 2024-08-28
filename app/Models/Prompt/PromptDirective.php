<?php

namespace App\Models\Prompt;

use App\Models\Agent\Agent;
use App\Models\Team\Team;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class PromptDirective extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes, KeywordSearchTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'agents_count',
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
        return $this->belongsToMany(Agent::class);
    }

    public function delete(): bool
    {
        $agentsCount = $this->agents()->count();
        if ($agentsCount) {
            throw new Exception("Cannot delete Prompt Directive $this->name: there are $agentsCount agents with this directive assigned.");
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

    public function __toString(): string
    {
        return "<PromptDirective $this->name>";
    }
}
