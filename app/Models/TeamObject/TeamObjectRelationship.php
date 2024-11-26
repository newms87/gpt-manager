<?php

namespace App\Models\TeamObject;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

/**
 * @property string $relationship_name
 * @property string $object_id
 * @property string $related_object_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 */
class TeamObjectRelationship extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes;

    protected $table   = 'team__object_relationships';
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

        $this->table = team()->namespace . '__object_relationships';

        parent::__construct($attributes);
    }

    public function related(): TeamObject|BelongsTo
    {
        return $this->belongsTo(TeamObject::class, 'related_object_id', 'id');
    }

    public function __toString(): string
    {
        return "<TeamObjectRelationship ($this->relationship_name) object_id='$this->object_id' related_id='$this->related_object_id' />";
    }
}
