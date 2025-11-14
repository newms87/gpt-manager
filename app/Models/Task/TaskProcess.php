<?php

namespace App\Models\Task;

use App\Events\TaskProcessUpdatedEvent;
use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaAssociation;
use App\Models\Traits\HasUsageTracking;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Runners\TaskRunnerContract;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Jobs\Job;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class TaskProcess extends Model implements AuditableContract, WorkflowStatesContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasRelationCountersTrait, HasUsageTracking, HasWorkflowStatesTrait, KeywordSearchTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'is_ready',
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'incomplete_at',
        'timeout_at',
        'percent_complete',
        'activity',
        'meta',
        'error_count',
    ];

    protected array $keywordFields = [
        'id',
        'status',
        'name',
        'activity',
        'agentThread.messages.content',
    ];

    public function casts(): array
    {
        return [
            'is_ready'         => 'boolean',
            'percent_complete' => 'float',
            'started_at'       => 'datetime',
            'stopped_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'failed_at'        => 'datetime',
            'incomplete_at'    => 'datetime',
            'timeout_at'       => 'datetime',
            'meta'             => 'array',
        ];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    public array $relationCounters = [
        JobDispatch::class => ['jobDispatches' => 'job_dispatch_count'],
        Artifact::class    => [
            'inputArtifacts'  => 'input_artifact_count',
            'outputArtifacts' => 'output_artifact_count',
        ],
    ];

    public function lastJobDispatch(): BelongsTo|JobDispatch
    {
        return $this->belongsTo(JobDispatch::class, 'last_job_dispatch_id');
    }

    public function taskRun(): BelongsTo|TaskRun
    {
        return $this->belongsTo(TaskRun::class);
    }

    /**
     * Get the team_id from the TaskRun->TaskDefinition relationship
     */
    public function getTeamIdAttribute(): ?string
    {
        return $this->taskRun?->taskDefinition?->team_id;
    }

    public function taskProcessListeners(): HasMany|TaskProcessListener
    {
        return $this->hasMany(TaskProcessListener::class);
    }

    public function agentThread(): BelongsTo|AgentThread
    {
        return $this->belongsTo(AgentThread::class, 'agent_thread_id');
    }

    public function jobDispatches(): MorphToMany
    {
        return $this->morphToMany(JobDispatch::class, 'model', 'job_dispatchables')->orderByDesc('id');
    }

    public function artifacts(): MorphToMany|Artifact
    {
        return $this->morphToMany(Artifact::class, 'artifactable')->withTimestamps()->orderBy('position');
    }

    public function inputArtifacts(): MorphToMany|Artifact
    {
        return $this->artifacts()->withPivotValue('category', 'input');
    }

    public function outputArtifacts(): MorphToMany|Artifact
    {
        return $this->artifacts()->withPivotValue('category', 'output');
    }

    public function outputSchemaAssociation(): MorphOne|SchemaAssociation
    {
        return $this->morphOne(SchemaAssociation::class, 'object')->where('category', 'output');
    }

    public function scopeReadyToRun(Builder $query): Builder
    {
        return $query->where('task_processes.is_ready', true)
            ->where(function (Builder $q) {
                // Pending processes
                $q->where('task_processes.status', WorkflowStatesContract::STATUS_PENDING)
                    // Or incomplete/timeout processes that can be retried
                    ->orWhere(function (Builder $retryQuery) {
                        $retryQuery->whereIn('task_processes.status', [WorkflowStatesContract::STATUS_INCOMPLETE, WorkflowStatesContract::STATUS_TIMEOUT])
                            ->whereHas('taskRun.taskDefinition', function (Builder $taskDefQuery) {
                                $taskDefQuery->whereColumn('task_processes.restart_count', '<', 'task_definitions.max_process_retries');
                            });
                    });
            });
    }

    public function addInputArtifacts($artifacts): static
    {
        $this->inputArtifacts()->syncWithoutDetaching(collect($artifacts)->pluck('id')->toArray());
        $this->updateRelationCounter('inputArtifacts');

        return $this;
    }

    public function addOutputArtifacts($artifacts): static
    {
        $this->outputArtifacts()->sync(collect($artifacts)->pluck('id'));
        $this->updateRelationCounter('inputArtifacts');

        return $this;
    }

    public function clearOutputArtifacts(): void
    {
        $this->outputArtifacts()->detach();
        $this->updateRelationCounter('outputArtifacts');
    }

    public function clearInputArtifacts(): void
    {
        $this->inputArtifacts()->detach();
        $this->updateRelationCounter('inputArtifacts');
    }

    public function isFailedAndCannotBeRetried(): bool
    {
        if ($this->isFailed() || $this->isTimeout() || $this->isIncomplete()) {
            return !$this->canBeRetried();
        }

        return false;
    }

    public function canBeRetried(): bool
    {
        return $this->restart_count < $this->taskRun->taskDefinition->max_process_retries;
    }

    public function isPastTimeout(): bool
    {
        if (!$this->started_at || $this->completed_at || $this->failed_at || $this->stopped_at) {
            return false;
        }

        return $this->started_at->addSeconds($this->taskRun?->taskDefinition->timeout_after_seconds)->isPast();
    }

    public function canBeRun(): bool
    {
        return $this->isStatusPending() && !$this->isStopped() && !$this->isIncomplete() && !$this->isCompleted() && !$this->isFailedAndCannotBeRetried();
    }

    public function canResume(): bool
    {
        // If the process is not currently running, it can be resumed. If it is running, it must first be stopped before resuming.
        return !$this->isStatusRunning();
    }

    public function computeStatus(): static
    {
        if ($this->isStopped()) {
            $this->status = WorkflowStatesContract::STATUS_STOPPED;
        } elseif ($this->isFailed()) {
            $this->status = WorkflowStatesContract::STATUS_FAILED;
        } elseif ($this->isIncomplete()) {
            $this->status = WorkflowStatesContract::STATUS_INCOMPLETE;
        } elseif ($this->isTimeout()) {
            $this->status = WorkflowStatesContract::STATUS_TIMEOUT;
        } elseif (!$this->isStarted()) {
            $this->status = WorkflowStatesContract::STATUS_PENDING;
        } elseif (!$this->isCompleted()) {
            $this->status = WorkflowStatesContract::STATUS_RUNNING;
        } else {
            $this->status = WorkflowStatesContract::STATUS_COMPLETED;
        }

        return $this;
    }

    /**
     * Get the TaskRunner class instance for the task process
     */
    public function getRunner(): TaskRunnerContract
    {
        return $this->taskRun->getRunner()->setTaskProcess($this);
    }

    public static function booted(): void
    {
        static::saving(function (TaskProcess $taskProcess) {
            // If process is marked incomplete but has exceeded retry limit, mark as permanently failed
            if ($taskProcess->isIncomplete() && $taskProcess->isFailedAndCannotBeRetried()) {
                $taskProcess->incomplete_at = null;
                $taskProcess->failed_at     = now();
            }

            $taskProcess->computeStatus();
        });

        static::saved(function (TaskProcess $taskProcess) {
            if ($taskProcess->wasChanged(['status', 'task_run_id']) || $taskProcess->wasRecentlyCreated) {
                $taskProcess->taskRun?->checkProcesses()->save();
            }

            if ($taskProcess->wasChanged([
                'status',
                'last_job_dispatch_id',
                'activity',
                'percent_complete',
                'input_artifact_count',
                'output_artifact_count',
                'restart_count',
            ])) {
                // If this is a task process job, we want to broadcast the status changes immediately to provide a better user experience
                // No need to spin up another job just to broadcast the status
                if ($taskProcess->wasChanged('status') && Job::$runningJob?->name === 'TaskProcessJob') {
                    TaskProcessUpdatedEvent::broadcast($taskProcess);
                } else {
                    TaskProcessUpdatedEvent::dispatch($taskProcess);
                }
            }
        });
    }

    public function __toString()
    {
        return "<TaskProcess id='$this->id' name='$this->name' status='$this->status' activity='$this->activity'>";
    }
}
