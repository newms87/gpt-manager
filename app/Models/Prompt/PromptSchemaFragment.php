<?php

namespace App\Models\Prompt;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class PromptSchemaFragment extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
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

    public function promptSchema(): BelongsTo|PromptSchema
    {
        return $this->belongsTo(PromptSchema::class);
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
                Rule::unique('prompt_schema_fragments')->where('prompt_schema_id', $this->prompt_schema_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function __toString(): string
    {
        return "<PromptSchemaFragment ($this->id) $this->name>";
    }
}
