<?php

namespace App\Models\Schema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class ArtifactCategoryDefinition extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, SoftDeletes;

    protected $fillable = [
        'schema_definition_id',
        'name',
        'label',
        'prompt',
        'fragment_selector',
        'editable',
        'deletable',
    ];

    public function casts(): array
    {
        return [
            'fragment_selector' => 'json',
            'editable'          => 'boolean',
            'deletable'         => 'boolean',
        ];
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('artifact_category_definitions')
                    ->where('schema_definition_id', $this->schema_definition_id)
                    ->whereNull('deleted_at')
                    ->ignore($this),
            ],
            'label'  => 'required|string|max:255',
            'prompt' => 'required|string',
        ])->validate();

        return $this;
    }

    public function __toString(): string
    {
        return "<ArtifactCategoryDefinition id='$this->id' name='$this->name'>";
    }
}
