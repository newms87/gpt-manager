<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectTagTaggable extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function objectTag(): BelongsTo|ObjectTag
    {
        return $this->belongsTo(ObjectTag::class);
    }
}
