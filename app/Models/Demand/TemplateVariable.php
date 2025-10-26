<?php

namespace App\Models\Demand;

use App\Models\Schema\SchemaAssociation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TemplateVariable extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, ActionModelTrait, AuditableTrait;

    // Mapping type constants
    public const MAPPING_TYPE_AI          = 'ai';
    public const MAPPING_TYPE_ARTIFACT    = 'artifact';
    public const MAPPING_TYPE_TEAM_OBJECT = 'team_object';

    // Multi-value strategy constants
    public const STRATEGY_JOIN   = 'join';
    public const STRATEGY_FIRST  = 'first';
    public const STRATEGY_UNIQUE = 'unique';
    public const STRATEGY_MAX    = 'max';
    public const STRATEGY_MIN    = 'min';
    public const STRATEGY_AVG    = 'avg';
    public const STRATEGY_SUM    = 'sum';

    // Format type constants
    public const FORMAT_TEXT       = 'text';
    public const FORMAT_INTEGER    = 'integer';
    public const FORMAT_DECIMAL    = 'decimal';
    public const FORMAT_CURRENCY   = 'currency';
    public const FORMAT_PERCENTAGE = 'percentage';
    public const FORMAT_DATE       = 'date';

    protected $fillable = [
        'demand_template_id',
        'name',
        'description',
        'mapping_type',
        'artifact_categories',
        'artifact_fragment_selector',
        'team_object_schema_association_id',
        'ai_instructions',
        'multi_value_strategy',
        'multi_value_separator',
        'value_format_type',
        'decimal_places',
        'currency_code',
    ];

    public function casts(): array
    {
        return [
            'artifact_categories'        => 'array',
            'artifact_fragment_selector' => 'array',
            'created_at'                 => 'datetime',
            'updated_at'                 => 'datetime',
            'deleted_at'                 => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function demandTemplate(): BelongsTo
    {
        return $this->belongsTo(DemandTemplate::class);
    }

    public function teamObjectSchemaAssociation(): BelongsTo
    {
        return $this->belongsTo(SchemaAssociation::class, 'team_object_schema_association_id');
    }

    /**
     * Scopes
     */
    protected static function booted(): void
    {
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('name');
        });
    }

    /**
     * Helper methods
     */
    public function isAiMapped(): bool
    {
        return $this->mapping_type === self::MAPPING_TYPE_AI;
    }

    public function isArtifactMapped(): bool
    {
        return $this->mapping_type === self::MAPPING_TYPE_ARTIFACT;
    }

    public function isTeamObjectMapped(): bool
    {
        return $this->mapping_type === self::MAPPING_TYPE_TEAM_OBJECT;
    }

    /**
     * Validation
     */
    public function validate(): static
    {
        $rules = [
            'demand_template_id'                => ['required', 'exists:demand_templates,id'],
            'name'                              => ['required', 'string', 'max:255'],
            'description'                       => ['nullable', 'string'],
            'mapping_type'                      => ['required', 'in:' . self::MAPPING_TYPE_AI . ',' . self::MAPPING_TYPE_ARTIFACT . ',' . self::MAPPING_TYPE_TEAM_OBJECT],
            'artifact_categories'               => ['nullable', 'array'],
            'artifact_fragment_selector'        => ['nullable', 'array'],
            'team_object_schema_association_id' => ['nullable', 'exists:schema_associations,id'],
            'ai_instructions'                   => ['nullable', 'string'],
            'multi_value_strategy'              => ['required', 'in:' . self::STRATEGY_JOIN . ',' . self::STRATEGY_FIRST . ',' . self::STRATEGY_UNIQUE . ',' . self::STRATEGY_MAX . ',' . self::STRATEGY_MIN . ',' . self::STRATEGY_AVG . ',' . self::STRATEGY_SUM],
            'value_format_type'                 => ['nullable', 'in:' . self::FORMAT_TEXT . ',' . self::FORMAT_INTEGER . ',' . self::FORMAT_DECIMAL . ',' . self::FORMAT_CURRENCY . ',' . self::FORMAT_PERCENTAGE . ',' . self::FORMAT_DATE],
            'decimal_places'                    => ['nullable', 'integer', 'min:0', 'max:4'],
            'currency_code'                     => ['nullable', 'string', 'size:3'],
            'multi_value_separator'             => ['required', 'string', 'max:255'],
        ];

        // Type-specific validation
        // Artifact mapping: categories and fragment_selector are optional - user can select all artifacts
        // TeamObject mapping: team_object_schema_association_id is optional - user can save incomplete configuration
        // AI mapping does not require ai_instructions - it's optional

        validator($this->toArray(), $rules)->validate();

        return $this;
    }
}
