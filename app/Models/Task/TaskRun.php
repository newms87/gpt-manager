<?php

namespace App\Models\Task;

use App\Models\Usage\UsageSummary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskRun extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $fillable = [
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
    ];

    public array $relationCounters = [
        TaskProcess::class => ['taskProcesses' => 'process_count'],
    ];

    public function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'stopped_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
        ];
    }

    public function taskDefinition(): TaskDefinition|BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function taskProcesses(): HasMany|TaskProcess
    {
        return $this->hasMany(TaskProcess::class);
    }

    public function taskInput(): BelongsTo|TaskInput
    {
        return $this->belongsTo(TaskInput::class);
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

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function canContinue(): bool
    {
        return !$this->isStopped() && !$this->isFailed() && !$this->isCompleted();
    }

    /**
     * Whenever a process state has changed, call this method to check if the task run has completed or has changed
     * state as well
     */
    public function checkProcesses(): void
    {
        // If we are already in an end state, we don't need to check the processes
        if (!$this->canContinue()) {
            return;
        }

        $hasRunningProcesses = false;

        foreach($this->taskProcesses()->get() as $taskProcess) {
            // If any process has failed or timed out, the task run has failed (we can stop checking)
            if ($taskProcess->isFailed() || $taskProcess->isTimeout()) {
                $this->failed_at = now();
                $this->save();

                return;
            } elseif (!$taskProcess->isFinished()) {
                $hasRunningProcesses = true;
            }
        }

        if (!$hasRunningProcesses && !$this->isFailed() && !$this->isStopped()) {
            $this->completed_at = now();
            $this->save();
        }
    }

    public function computeStatus(): static
    {
        if (!$this->isStarted()) {
            $this->status = TaskProcess::STATUS_PENDING;
        } elseif ($this->isFailed()) {
            $this->status = TaskProcess::STATUS_FAILED;
        } elseif ($this->isStopped()) {
            $this->status = TaskProcess::STATUS_STOPPED;
        } elseif (!$this->isCompleted()) {
            $this->status = TaskProcess::STATUS_RUNNING;
        } else {
            $this->status = TaskProcess::STATUS_COMPLETED;
        }

        return $this;
    }

    public static function booted(): void
    {
        static::saving(function (TaskRun $taskRun) {
            $taskRun->computeStatus();
        });
    }

    public function __toString()
    {
        return "<TaskRun id='$this->id' name='{$this->taskDefinition->name}' status='$this->status' processes='$this->process_count'>";
    }
}
