<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowInputAssociation extends Model
{
    protected $fillable = [
        'workflow_input_id',
        'associable_type',
        'associable_id',
        'category',
    ];

    public function workflowInput(): BelongsTo
    {
        return $this->belongsTo(WorkflowInput::class);
    }

    public function associable(): MorphTo
    {
        return $this->morphTo();
    }
}
