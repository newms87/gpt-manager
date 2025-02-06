<?php

namespace App\Models\Task;

use App\Models\Agent\AgentThread;
use App\Models\Usage\UsageSummary;
use App\Models\Workflow\Artifact;
use App\Services\Task\Runners\TaskRunnerContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskProcess extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    const string
        STATUS_PENDING = 'Pending',
        STATUS_DISPATCHED = 'Dispatched',
        STATUS_RUNNING = 'Running',
        STATUS_STOPPED = 'Stopped',
        STATUS_COMPLETED = 'Completed',
        STATUS_TIMEOUT = 'Timeout',
        STATUS_FAILED = 'Failed';

    const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DISPATCHED,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_TIMEOUT,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'name',
        'task_definition_agent_id',
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'timeout_at',
        'percent_complete',
        'activity',
    ];

    public function casts(): array
    {
        return [
            'percent_complete' => 'float',
            'started_at'       => 'datetime',
            'stopped_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'failed_at'        => 'datetime',
            'timeout_at'       => 'datetime',
        ];
    }

    public array $relationCounters = [
        JobDispatch::class => ['jobDispatches' => 'job_dispatch_count'],
        Artifact::class    => [
            'inputArtifacts'  => 'input_artifact_count',
            'outputArtifacts' => 'output_artifact_count',
        ],
    ];

    public function taskRun(): BelongsTo|TaskRun
    {
        return $this->belongsTo(TaskRun::class);
    }

    public function taskProcessListeners(): HasMany|TaskProcessListener
    {
        return $this->hasMany(TaskProcessListener::class);
    }

    public function taskDefinitionAgent(): BelongsTo|TaskDefinitionAgent
    {
        return $this->belongsTo(TaskDefinitionAgent::class);
    }

    public function agentThread(): BelongsTo|AgentThread
    {
        return $this->belongsTo(AgentThread::class, 'agent_thread_id');
    }

    public function jobDispatches(): MorphToMany
    {
        return $this->morphToMany(JobDispatch::class, 'model', 'job_dispatchables');
    }

    public function artifacts(): MorphToMany|Artifact
    {
        return $this->morphToMany(Artifact::class, 'artifactable')->withTimestamps();
    }

    public function inputArtifacts(): MorphToMany|Artifact
    {
        return $this->artifacts()->withPivotValue('category', 'input');
    }

    public function outputArtifacts(): MorphToMany|Artifact
    {
        return $this->artifacts()->withPivotValue('category', 'output');
    }

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object');
    }

    public function isPending(): bool
    {
        return $this->status === TaskProcess::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === TaskProcess::STATUS_RUNNING;
    }

    public function isDispatched(): bool
    {
        return $this->last_job_dispatch_id !== null;
    }

    public function isStarted(): bool
    {
        return $this->started_at !== null;
    }

    public function isStopped(): bool
    {
        return $this->stopped_at !== null;
    }

    public function isFailed(): bool
    {
        return $this->failed_at !== null;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isStopped() || $this->isTimeout();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isTimeout(): bool
    {
        return $this->timeout_at !== null;
    }

    public function isPastTimeout(): bool
    {
        if (!$this->started_at || $this->completed_at || $this->failed_at || $this->stopped_at) {
            return false;
        }

        return $this->started_at->addSeconds($this->taskRun->taskDefinition->timeout_after_seconds)->isPast();
    }

    public function canBeRun(): bool
    {
        return $this->isDispatched() && !$this->isStarted() && !$this->isStopped() && !$this->isFailed() && !$this->isCompleted() && !$this->isTimeout();
    }

    public function computeStatus(): static
    {
        if (!$this->isDispatched()) {
            $this->status = TaskProcess::STATUS_PENDING;
        } elseif (!$this->isStarted()) {
            $this->status = TaskProcess::STATUS_DISPATCHED;
        } elseif ($this->isFailed()) {
            $this->status = TaskProcess::STATUS_FAILED;
        } elseif ($this->isStopped()) {
            $this->status = TaskProcess::STATUS_STOPPED;
        } elseif ($this->isTimeout()) {
            $this->status = TaskProcess::STATUS_TIMEOUT;
        } elseif (!$this->isCompleted()) {
            $this->status = TaskProcess::STATUS_RUNNING;
        } else {
            $this->status = TaskProcess::STATUS_COMPLETED;
        }

        return $this;
    }

    /**
     * Get the TaskRunner class instance for the task process
     */
    public function getRunner(): TaskRunnerContract
    {
        return $this->taskRun->getRunner($this);
    }

    public static function booted(): void
    {
        static::saving(function (TaskProcess $taskProcess) {
            $taskProcess->computeStatus();
        });

        static::saved(function (TaskProcess $taskProcess) {
            if ($taskProcess->wasChanged('status')) {
                $taskProcess->taskRun->checkProcesses();
            }
        });
    }

    public function __toString()
    {
        return "<TaskProcess id='$this->id' name='$this->name' status='$this->status' activity='$this->activity'>";
    }
}
