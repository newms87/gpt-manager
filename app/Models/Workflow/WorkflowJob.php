<?php

namespace App\Models\Workflow;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Flytedan\DanxLaravel\Traits\CountableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowJob extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, AuditableTrait, CountableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public array $relatedCounters = [
        Workflow::class => 'jobs_count',
    ];

    public function casts()
    {
        return [
            'depends_on' => 'array',
        ];
    }

    public function workflow(): BelongsTo|Workflow
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowJobRuns(): HasMany|WorkflowJobRun
    {
        return $this->hasMany(WorkflowJobRun::class);
    }

    public function workflowTasks(): HasMany|WorkflowTask
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function remainingTasks(): HasMany|WorkflowTask
    {
        return $this->hasMany(WorkflowTask::class)->whereIn('status', [WorkflowTask::STATUS_PENDING, WorkflowTask::STATUS_RUNNING]);
    }

    public function workflowAssignments(): HasMany|WorkflowAssignment
    {
        return $this->hasMany(WorkflowAssignment::class);
    }

    public function __toString()
    {
        return "<Workflow Job ($this->id) $this->name>";
    }
}
