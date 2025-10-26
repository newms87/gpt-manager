<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Artifactable extends MorphPivot
{
    protected $table   = 'artifactables';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function artifact(): BelongsTo|Artifact
    {
        return $this->belongsTo(Artifact::class);
    }
}
