<?php

namespace App\Models\Workflow;

use App\Models\Shared\Artifact;
use App\Models\Shared\InputSource;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Contracts\ComputedStatusContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\CountableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowRun extends Model implements AuditableContract, ComputedStatusContract
{
    use HasFactory, SoftDeletes, AuditableTrait, CountableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public array $relatedCounters = [
        Workflow::class    => 'runs_count',
        InputSource::class => 'workflow_runs_count',
    ];

    const string
        STATUS_PENDING = 'Pending',
        STATUS_RUNNING = 'Running',
        STATUS_COMPLETED = 'Completed',
        STATUS_FAILED = 'Failed';

    const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    public static function booted(): void
    {
        static::saving(function (WorkflowRun $workflowRun) {
            $workflowRun->computeStatus();
        });
    }

    public function casts()
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
        ];
    }

    public function workflow(): BelongsTo|Workflow
    {
        return $this->belongsTo(Workflow::class);
    }

    public function inputSource(): BelongsTo|InputSource
    {
        return $this->belongsTo(InputSource::class);
    }

    public function workflowJobRuns(): HasMany|WorkflowJobRun
    {
        return $this->hasMany(WorkflowJobRun::class);
    }

    public function sortedWorkflowJobRuns(): HasMany|WorkflowJobRun
    {
        // Order by started_at, but if started_at is null, sort to the bottom
        return $this->workflowJobRuns()->orderByRaw('started_at IS NULL, started_at');
    }

    public function runningJobRuns(): HasMany|WorkflowJobRun
    {
        return $this->workflowJobRuns()->where('status', self::STATUS_RUNNING);
    }

    public function remainingJobRuns(): HasMany|WorkflowJobRun
    {
        return $this->workflowJobRuns()->whereIn('status', [WorkflowRun::STATUS_PENDING, WorkflowRun::STATUS_RUNNING]);
    }

    public function artifacts()
    {
        return $this->morphToMany(Artifact::class, 'artifactable');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function computeStatus(): static
    {
        if ($this->started_at === null) {
            $this->status = self::STATUS_PENDING;
        } elseif ($this->failed_at !== null) {
            $this->status = self::STATUS_FAILED;
        } elseif ($this->completed_at === null) {
            $this->status = self::STATUS_RUNNING;
        } else {
            $this->status = self::STATUS_COMPLETED;
        }

        return $this;
    }

    public function getTotalInputTokens()
    {
        return $this->workflowJobRuns->sum(fn(WorkflowJobRun $jobRun) => $jobRun->getTotalInputTokens());
    }

    public function getTotalOutputTokens()
    {
        return $this->workflowJobRuns->sum(fn(WorkflowJobRun $jobRun) => $jobRun->getTotalOutputTokens());
    }

    public function getTotalCost()
    {
        return $this->workflowJobRuns->sum(fn(WorkflowJobRun $jobRun) => $jobRun->getTotalCost());
    }

    public function __toString()
    {
        return "<WorkflowRun $this->id [$this->status]>";
    }
}
