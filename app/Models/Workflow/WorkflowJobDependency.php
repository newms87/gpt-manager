<?php

namespace App\Models\Workflow;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowJobDependency extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function workflowJob(): BelongsTo|WorkflowJob
    {
        return $this->belongsTo(WorkflowJob::class);
    }

    public function dependsOn(): BelongsTo|WorkflowJob
    {
        return $this->belongsTo(WorkflowJob::class, 'depends_on_workflow_job_id');
    }

    public function __toString()
    {
        $dependent = $this->workflowJob->name;
        $dependsOn = $this->dependsOn->name;

        return "<WorkflowJobDependency ($this->id) $dependent --depends on--> $dependsOn>";
    }
}
