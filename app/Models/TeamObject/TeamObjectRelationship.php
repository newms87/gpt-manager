<?php

namespace App\Models\TeamObject;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TeamObjectRelationship extends Model implements AuditableContract
{
    use AuditableTrait, ActionModelTrait, HasFactory, SoftDeletes;

    protected $table   = 'team_object_relationships';
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function related(): TeamObject|BelongsTo
    {
        return $this->belongsTo(TeamObject::class, 'related_team_object_id', 'id');
    }

    public function __toString(): string
    {
        return "<TeamObjectRelationship ($this->relationship_name) object_id='$this->team_object_id' related_id='$this->related_team_object_id' />";
    }
}
