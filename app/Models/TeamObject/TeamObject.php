<?php

namespace App\Models\TeamObject;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

/**
 * @property int    $id
 * @property string $type
 * @property string $name
 * @property string $description
 * @property string $url
 * @property array  $meta
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
        ];
    }

    public function relationships(): HasMany|TeamObjectRelationship
    {
        return $this->hasMany(TeamObjectRelationship::class, 'object_id');
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

    public function __toString(): string
    {
        return "<TeamObject ($this->type) id='$this->id' name='$this->name' />";
    }
}
