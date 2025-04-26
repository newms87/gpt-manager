<?php

namespace App\Models\Task;

use App\Events\TaskProcessUpdatedEvent;
use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaAssociation;
use App\Models\Usage\UsageSummary;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Runners\TaskRunnerContract;
use App\Traits\HasWorkflowStatesTrait;
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
    use HasFactory, AuditableTrait, ActionModelTrait, HasRelationCountersTrait, SoftDeletes, KeywordSearchTrait, HasWorkflowStatesTrait;

    protected $fillable = [
        'name',
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'timeout_at',
        'percent_complete',
        'activity',
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
            'percent_complete' => 'float',
            'started_at'       => 'datetime:Y-m-d H:i:s.v',
            'stopped_at'       => 'datetime:Y-m-d H:i:s.v',
            'completed_at'     => 'datetime:Y-m-d H:i:s.v',
            'failed_at'        => 'datetime:Y-m-d H:i:s.v',
            'timeout_at'       => 'datetime:Y-m-d H:i:s.v',
            'created_at'       => 'datetime:Y-m-d H:i:s.v',
            'updated_at'       => 'datetime:Y-m-d H:i:s.v',
            'deleted_at'       => 'datetime:Y-m-d H:i:s.v',
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

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object');
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

    public function isDispatched(): bool
    {
        return $this->last_job_dispatch_id !== null;
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
        return $this->isDispatched() && !$this->isStarted() && !$this->isStopped() && !$this->isFailed() && !$this->isCompleted() && !$this->isTimedout();
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
        } elseif ($this->isTimedout()) {
            $this->status = WorkflowStatesContract::STATUS_TIMEOUT;
        } elseif (!$this->isDispatched()) {
            $this->status = WorkflowStatesContract::STATUS_PENDING;
        } elseif (!$this->isStarted()) {
            $this->status = WorkflowStatesContract::STATUS_DISPATCHED;
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
            $taskProcess->computeStatus();
        });

        static::saved(function (TaskProcess $taskProcess) {
            if ($taskProcess->wasChanged('status')) {
                $taskProcess->taskRun->checkProcesses()->save();
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
                // If this is the execute task process job, we want to broadcast the status changes immediately to provide a better user experience
                // No need to spin up another job just to broadcast the status
                if ($taskProcess->wasChanged('status') && Job::$runningJob?->name === 'ExecuteTaskProcessJob') {
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
