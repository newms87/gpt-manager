<?php

namespace App\Models\Schema;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Services\Workflow\WorkflowExportServiceInterface;
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

class SchemaFragment extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasRelationCountersTrait, ResourcePackageableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'fragment_selector',
    ];

    public array $relationCounters = [
        SchemaAssociation::class => ['associations' => 'associations_count'],
    ];

    public function casts(): array
    {
        return [
            'fragment_selector' => 'json',
        ];
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }

    public function associations(): HasMany|SchemaAssociation
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
                Rule::unique('schema_fragments')->where('schema_definition_id', $this->schema_definition_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function exportToJson(WorkflowExportServiceInterface $service): int
    {
        return $service->register($this, [
            'schema_definition_id' => $service->registerRelatedModel($this->schemaDefinition),
            'name'                 => $this->name,
            'fragment_selector'    => $this->fragment_selector,
        ]);
    }

    public function __toString(): string
    {
        return "<SchemaFragment id='$this->id' name='$this->name'>";
    }
}
