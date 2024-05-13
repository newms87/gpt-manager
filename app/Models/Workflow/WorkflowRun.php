<?php

namespace App\Models\Workflow;

use App\Models\Shared\InputSource;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Contracts\ComputedStatusContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Flytedan\DanxLaravel\Traits\CountableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public static function booted()
    {
        static::saving(function ($workflowTask) {
            $workflowTask->computeStatus();
        });
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function inputSource()
    {
        return $this->belongsTo(InputSource::class);
    }

    public function pendingTasks()
    {
        return $this->hasMany(WorkflowTask::class)->where('status', WorkflowTask::STATUS_PENDING);
    }
    
    public function remainingTasks()
    {
        return $this->hasMany(WorkflowTask::class)->whereIn('status', [WorkflowTask::STATUS_PENDING, WorkflowTask::STATUS_RUNNING]);
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
}
