<?php

namespace App\Models\Schema;

use App\Models\Agent\Agent;
use App\Models\Team\Team;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class SchemaDefinition extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait, HasRelationCountersTrait, SoftDeletes, KeywordSearchTrait;

    const string
        FORMAT_JSON = 'json',
        FORMAT_YAML = 'yaml',
        FORMAT_TYPESCRIPT = 'typescript';

    const string
        TYPE_AGENT_RESPONSE = 'Agent Response';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'agents_count',
    ];

    protected array $keywordFields = [
        'type',
        'name',
        'description',
        'schema_format',
    ];

    public array $relationCounters = [
        Agent::class             => ['agents' => 'agents_count'],
        SchemaFragment::class    => ['fragments' => 'fragments_count'],
        SchemaAssociation::class => ['associations' => 'associations_count'],
    ];

    public function casts(): array
    {
        return [
            'schema'           => 'json',
            'response_example' => 'json',
        ];
    }

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function agents(): HasMany|Agent
    {
        return $this->hasMany(Agent::class, 'response_schema_id');
    }

    public function schemaDefinitionRevisions(): HasMany|SchemaHistory
    {
        return $this->hasMany(SchemaHistory::class);
    }

    public function fragments(): SchemaFragment|HasMany
    {
        return $this->hasMany(SchemaFragment::class);
    }

    public function associations(): SchemaAssociation|HasMany
    {
        return $this->hasMany(SchemaAssociation::class);
    }

    public function delete(): bool
    {
        $agentsCount = $this->agents()->count();
        if ($agentsCount) {
            throw new Exception("Cannot delete Schema Definition $this->name: there are $agentsCount agents with this schema assigned.");
        }

        return parent::delete();
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('schema_definitions')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'type' => 'required|string',
        ])->validate();

        return $this;
    }

    public static function booted(): void
    {
        static::creating(function (SchemaDefinition $schemaDefinition) {
            $schemaDefinition->team_id = $schemaDefinition->team_id ?? team()->id ?? null;
        });

        static::updated(function (SchemaDefinition $schemaDefinition) {
            // Track Schema History if it was changed and there was a previous version
            if ($schemaDefinition->wasChanged('schema')) {
                SchemaHistory::write(user(), $schemaDefinition, $schemaDefinition->getOriginal('schema') ?: []);
            }
        });
    }

    public function __toString(): string
    {
        return "<SchemaDefinition $this->name>";
    }
}
