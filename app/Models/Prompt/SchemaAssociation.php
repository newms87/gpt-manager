<?php

namespace App\Models\Prompt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaAssociation extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function promptSchema(): BelongsTo|PromptSchema
    {
        return $this->belongsTo(PromptSchema::class);
    }

    public function promptSchemaFragment(): BelongsTo|PromptSchemaFragment
    {
        return $this->belongsTo(PromptSchemaFragment::class);
    }
}
