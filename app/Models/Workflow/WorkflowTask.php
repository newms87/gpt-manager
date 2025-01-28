<?php

namespace App\Models\Workflow;

use App\Models\Agent\AgentThread;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Contracts\ComputedStatusContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowTask extends Model implements AuditableContract, ComputedStatusContract
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
        static::saving(function ($workflowTask) {
            $workflowTask->computeStatus();
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

    public function workflowJobRun(): BelongsTo|WorkflowJobRun
    {
        return $this->belongsTo(WorkflowJobRun::class);
    }

    public function workflowJob(): BelongsTo|WorkflowJob
    {
        return $this->belongsTo(WorkflowJob::class);
    }

    public function workflowAssignment(): BelongsTo|WorkflowAssignment
    {
        return $this->belongsTo(WorkflowAssignment::class);
    }

    public function thread(): BelongsTo|AgentThread
    {
        return $this->belongsTo(AgentThread::class);
    }

    public function artifacts(): MorphToMany|Artifact
    {
        return $this->morphToMany(Artifact::class, 'artifactable')->withTimestamps();
    }

    public function jobDispatch(): BelongsTo|JobDispatch
    {
        return $this->belongsTo(JobDispatch::class);
    }

    public function isComplete(): bool
    {
        return $this->status === WorkflowRun::STATUS_COMPLETED;
    }

    public function computeStatus(): static
    {
        if ($this->started_at === null) {
            $this->status = WorkflowRun::STATUS_PENDING;
        } elseif ($this->isTimedOut()) {
            $this->status = WorkflowRun::STATUS_TIMED_OUT;
        } elseif ($this->failed_at !== null) {
            $this->status = WorkflowRun::STATUS_FAILED;
        } elseif ($this->completed_at === null) {
            $this->status = WorkflowRun::STATUS_RUNNING;
        } else {
            $this->status = WorkflowRun::STATUS_COMPLETED;
        }

        return $this;
    }

    public function isTimedOut(): bool
    {
        if ($this->status === WorkflowRun::STATUS_TIMED_OUT) {
            return true;
        }

        if (!$this->started_at || $this->completed_at) {
            return false;
        }

        return $this->started_at->addSeconds($this->workflowJob?->timeout_after ?: 0)->isPast();
    }

    public function getTotalInputTokens()
    {
        return $this->thread?->getTotalInputTokens() ?? 0;
    }

    public function getTotalOutputTokens()
    {
        return $this->thread?->getTotalOutputTokens() ?? 0;
    }

    public function getTotalCost()
    {
        return $this->thread?->getTotalCost() ?? 0;
    }

    public function __toString()
    {
        return "<WorkflowTask $this->id {$this->workflowJob->name} group=$this->group [$this->status]>";
    }
}
