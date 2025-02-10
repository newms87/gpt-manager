<?php

namespace App\Models\Schema;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SchemaAssociation extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
        'object_id',
        'object_type',
        'created_at',
        'updated_at',
    ];

    public function associatedObject(): MorphTo
    {
        return $this->morphTo('object');
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }

    public function schemaFragment(): BelongsTo|SchemaFragment
    {
        return $this->belongsTo(SchemaFragment::class);
    }
}
