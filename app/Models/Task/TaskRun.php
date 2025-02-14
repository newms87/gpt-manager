<?php

namespace App\Models\Task;

use App\Models\Usage\UsageSummary;
use App\Services\Task\Runners\TaskRunnerBase;
use App\Services\Task\Runners\TaskRunnerContract;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskRun extends Model implements AuditableContract, WorkflowStatesContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, HasWorkflowStatesTrait, SoftDeletes;

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
            'percent_complete' => 'float',
            'started_at'       => 'datetime',
            'stopped_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'failed_at'        => 'datetime',
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

    public function taskWorkflowRun(): BelongsTo|TaskWorkflow
    {
        return $this->belongsTo(TaskWorkflowRun::class);
    }

    public function taskWorkflowNode(): BelongsTo|TaskWorkflowNode
    {
        return $this->belongsTo(TaskWorkflowNode::class);
    }

    public function collectInputArtifacts(): Collection
    {
        $artifacts = collect();
        foreach($this->taskProcesses as $taskProcess) {
            $artifacts = $artifacts->merge($taskProcess->inputArtifacts);
        }

        return $artifacts;
    }

    public function collectOutputArtifacts(): Collection
    {
        $artifacts = collect();
        foreach($this->taskProcesses as $taskProcess) {
            $artifacts = $artifacts->merge($taskProcess->outputArtifacts);
        }

        return $artifacts;
    }

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object');
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

    /**
     * Get the TaskRunner class instance for the task run
     */
    public function getRunner(TaskProcess $taskProcess = null): TaskRunnerContract
    {
        $runners     = config('ai.runners');
        $runnerClass = $runners[$this->taskDefinition->task_runner_class] ?? TaskRunnerBase::class;

        return app()->makeWith($runnerClass, ['taskRun' => $this, 'taskProcess' => $taskProcess]);
    }

    public static function booted(): void
    {
        static::saving(function (TaskRun $taskRun) {
            $taskRun->computeStatus();
        });

        static::saved(function (TaskRun $taskRun) {
            if ($taskRun->wasChanged('status')) {
                $taskRun->taskWorkflowRun?->checkTaskRuns();
            }
        });
    }

    public function __toString()
    {
        return "<TaskRun id='$this->id' name='$this->name' status='$this->status' step='$this->step' processes='$this->process_count'>";
    }
}
