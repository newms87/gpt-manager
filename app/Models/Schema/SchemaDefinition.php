<?php

namespace App\Models\Schema;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Team\Team;
use App\Services\Workflow\WorkflowExportService;
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

class SchemaDefinition extends Model implements AuditableContract, ResourcePackageableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait, ResourcePackageableTrait, HasRelationCountersTrait, SoftDeletes, KeywordSearchTrait;

    const string
        FORMAT_JSON = 'json',
        FORMAT_YAML = 'yaml',
        FORMAT_TYPESCRIPT = 'typescript';

    const string
        TYPE_AGENT_RESPONSE = 'Agent Response';

    protected $fillable = [
        'type',
        'name',
        'description',
        'schema_format',
        'schema',
    ];

    protected array $keywordFields = [
        'type',
        'name',
        'description',
        'schema_format',
    ];

    public array $relationCounters = [
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
        static::updated(function (SchemaDefinition $schemaDefinition) {
            // Track Schema History if it was changed and there was a previous version
            if ($schemaDefinition->wasChanged('schema')) {
                SchemaHistory::write(user(), $schemaDefinition, $schemaDefinition->getOriginal('schema') ?: []);
            }
        });
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'type'          => $this->type,
            'name'          => $this->name,
            'description'   => $this->description,
            'schema_format' => $this->schema_format,
            'schema'        => $this->schema,
        ]);
    }

    public function __toString(): string
    {
        return "<SchemaDefinition id='$this->id' name='$this->name'>";
    }
}
