<?php

namespace App\Models\Schema;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Newms87\Danx\Traits\ActionModelTrait;

class SchemaAssociation extends Model
{
    use HasFactory, ActionModelTrait;

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

    public function exportToJson(): array
    {
        return [
            'category'         => $this->category,
            'schemaDefinition' => $this->schemaDefinition->exportToJson(),
            'schemaFragment'   => $this->schemaFragment->exportToJson(),
        ];
    }
}
