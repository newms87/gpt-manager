<?php

namespace App\Models\TeamObject;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Traits\AuditableTrait;

/**
 * @property int    $id
 * @property string $type
 * @property string $name
 * @property Carbon $date
 * @property string $description
 * @property string $url
 * @property array  $meta
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon deleted_at
 */
class TeamObject extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes;

    protected $table   = 'team__objects';
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function __construct(array $attributes = [])
    {
        if (!team()->namespace) {
            throw new Exception("Cannot instantiate " . static::class . ": Team namespace is not set");
        }

        $this->table = team()->namespace . '__objects';
        parent::__construct($attributes);
    }

    public function casts(): array
    {
        return [
            'meta' => 'json',
            'date' => 'datetime',
        ];
    }

    public function relationships(): HasMany|TeamObjectRelationship
    {
        return $this->hasMany(TeamObjectRelationship::class, 'object_id');
    }

    public function relatedToMe(): HasMany|TeamObject
    {
        return $this->hasMany(TeamObjectRelationship::class, 'related_object_id');
    }

    public function relatedObjects($relationshipName): TeamObject|HasManyThrough
    {
        return $this->hasManyThrough(TeamObject::class, TeamObjectRelationship::class, 'object_id', 'id', 'id', 'related_object_id')
            ->where('relationship_name', $relationshipName);
    }

    public function attributes(): HasMany|TeamObjectAttribute
    {
        return $this->hasMany(TeamObjectAttribute::class, 'object_id');
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
        if (TeamObject::where('type', $this->type)->where('name', $this->name)->where('id', '!=', $this->id)->exists()) {
            throw new ValidationError("A $this->type with the name $this->name already exists");
        }

        return $this;
    }

    public function __toString(): string
    {
        return "<TeamObject ($this->type) id='$this->id' name='$this->name' />";
    }
}
