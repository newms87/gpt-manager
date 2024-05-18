<?php

namespace App\Models\Workflow;

use App\Models\Shared\Artifact;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Contracts\ComputedStatusContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowJobRun extends Model implements AuditableContract, ComputedStatusContract
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public static function booted(): void
    {
        static::saving(function (WorkflowJobRun $workflowJobRun) {
            $workflowJobRun->computeStatus();
        });
    }

    public function workflowRun(): BelongsTo|WorkflowRun
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function workflowJob(): BelongsTo|WorkflowJob
    {
        return $this->belongsTo(WorkflowJob::class);
    }

    public function tasks(): HasMany|WorkflowTask
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function pendingTasks(): HasMany|WorkflowTask
    {
        return $this->tasks()->where('status', WorkflowTask::STATUS_PENDING);
    }

    public function remainingTasks(): HasMany|WorkflowTask
    {
        return $this->tasks()->whereIn('status', [WorkflowTask::STATUS_PENDING, WorkflowTask::STATUS_RUNNING]);
    }

    public function artifacts()
    {
        return $this->morphToMany(Artifact::class, 'artifactable');
    }

    public function isComplete(): bool
    {
        return $this->status === WorkflowRun::STATUS_COMPLETED;
    }

    public function computeStatus(): static
    {
        if ($this->started_at === null) {
            $this->status = WorkflowRun::STATUS_PENDING;
        } elseif ($this->failed_at !== null) {
            $this->status = WorkflowRun::STATUS_FAILED;
        } elseif ($this->completed_at === null) {
            $this->status = WorkflowRun::STATUS_RUNNING;
        } else {
            $this->status = WorkflowRun::STATUS_COMPLETED;
        }

        return $this;
    }

    public function __toString()
    {
        return "<WorkflowJobRun $this->id [$this->status]>";
    }
}
