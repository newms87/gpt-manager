<?php

namespace App\Models\TeamObject;

use App\Events\TeamObjectUpdatedEvent;
use App\Models\Schema\SchemaDefinition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TeamObject extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, SoftDeletes;

    protected $table   = 'team_objects';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'meta' => 'json',
            'date' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::saved(function (TeamObject $teamObject) {
            TeamObjectUpdatedEvent::broadcast($teamObject);
        });
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class, 'schema_definition_id');
    }

    public function rootObject(): BelongsTo|TeamObject
    {
        return $this->belongsTo(TeamObject::class, 'root_object_id');
    }

    public function relationships(): HasMany|TeamObjectRelationship
    {
        return $this->hasMany(TeamObjectRelationship::class, 'team_object_id');
    }

    public function relatedToMe(): HasMany|TeamObject
    {
        return $this->hasMany(TeamObjectRelationship::class, 'related_team_object_id');
    }

    public function relatedObjects($relationshipName): TeamObject|HasManyThrough
    {
        return $this->hasManyThrough(TeamObject::class, TeamObjectRelationship::class, 'team_object_id', 'id', 'id', 'related_team_object_id')
            ->where('relationship_name', $relationshipName);
    }

    public function attributes(): HasMany|TeamObjectAttribute
    {
        return $this->hasMany(TeamObjectAttribute::class, 'team_object_id');
    }

    public function delete()
    {
        $this->attributes()->delete();
        $this->relationships()->delete();
        $this->relatedToMe()->delete();

        return parent::delete();
    }

    public function validate(): static
    {
        $query = TeamObject::where('type', $this->type)->where('name', $this->name)->where('id', '!=', $this->id);

        // Add date to uniqueness check - same name with different dates are allowed
        if ($this->date) {
            $query->whereDate('date', $this->date);
        } else {
            $query->whereNull('date');
        }

        // If a schema is set, only allow one object with the same name and schema,
        // Otherwise, there should be only 1 w/ a null schema (aka: belongs to global namespace)
        if ($this->schema_definition_id) {
            $query->where('schema_definition_id', $this->schema_definition_id);
        } else {
            $query->whereNull('schema_definition_id');
        }

        if ($this->root_object_id) {
            $query->where('root_object_id', $this->root_object_id);
        } else {
            $query->whereNull('root_object_id');
        }

        if ($existingObject = $query->first()) {
            $dateStr = $this->date ? $this->date->format('Y-m-d') : 'null';

            throw new ValidationError(
                "A {$this->type} already exists with: " .
                "name='{$this->name}', date='{$dateStr}', " .
                'root_object_id=' . ($this->root_object_id ?? 'null') . ', ' .
                'schema_definition_id=' . ($this->schema_definition_id ?? 'null') . '. ' .
                "Existing: {$existingObject}",
                409
            );
        }

        return $this;
    }

    public function __toString(): string
    {
        return "<TeamObject id='$this->id' name='$this->name' type='$this->type'>";
    }
}
