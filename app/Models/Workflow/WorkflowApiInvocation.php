<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowApiInvocation extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'payload' => 'json',
        ];
    }

    public function workflowRun(): BelongsTo|WorkflowRun
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function __toString()
    {
        return "<WorkflowApiInvocation id='$this->id' name='$this->name'>";
    }
}
