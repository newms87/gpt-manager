<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Contracts\ComputedStatusContract;
use Newms87\Danx\Traits\AuditableTrait;

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

    public function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
        ];
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

    public function completedTasks(): HasMany|WorkflowTask
    {
        return $this->tasks()->where('status', WorkflowTask::STATUS_COMPLETED);
    }

    public function artifacts(): MorphToMany|Artifact
    {
        return $this->morphToMany(Artifact::class, 'artifactable')->withTimestamps();
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

    public function getTotalInputTokens()
    {
        return $this->tasks->sum(fn(WorkflowTask $task) => $task->getTotalInputTokens());
    }

    public function getTotalOutputTokens()
    {
        return $this->tasks->sum(fn(WorkflowTask $task) => $task->getTotalOutputTokens());
    }

    public function getTotalCost()
    {
        return $this->tasks->sum(fn(WorkflowTask $task) => $task->getTotalCost());
    }

    public function __toString()
    {
        return "<WorkflowJobRun ($this->id) {$this->workflowJob->name} [$this->status]>";
    }
}
