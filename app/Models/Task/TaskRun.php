<?php

namespace App\Models\Task;

use App\Events\TaskRunUpdatedEvent;
use App\Models\Traits\HasUsageTracking;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Runners\TaskRunnerContract;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use App\Traits\HasDebugLogging;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Jobs\Job;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskRun extends Model implements AuditableContract, WorkflowStatesContract
{
    use ActionModelTrait, AuditableTrait, HasDebugLogging, HasFactory, HasRelationCountersTrait, HasUsageTracking, HasWorkflowStatesTrait, SoftDeletes;

    protected $fillable = [
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'skipped_at',
        'task_input_id',
        'task_process_error_count',
    ];

    public array $relationCounters = [
        TaskProcess::class => ['taskProcesses' => 'process_count'],
        Artifact::class    => [
            'inputArtifacts'  => 'input_artifacts_count',
            'outputArtifacts' => 'output_artifacts_count',
        ],
    ];

    public function casts(): array
    {
        return [
            'meta'             => 'array',
            'percent_complete' => 'float',
            'started_at'       => 'datetime',
            'stopped_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'failed_at'        => 'datetime',
            'skipped_at'       => 'datetime',
        ];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
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

    public function workflowRun(): BelongsTo|WorkflowDefinition
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function workflowNode(): BelongsTo|WorkflowNode
    {
        return $this->belongsTo(WorkflowNode::class);
    }

    public function artifactables(): MorphMany|Artifactable
    {
        return $this->morphMany(Artifactable::class, 'artifactable');
    }

    public function inputArtifactables(): MorphMany|Artifactable
    {
        return $this->artifactables()->where('category', 'input');
    }

    public function outputArtifactables(): MorphMany|Artifactable
    {
        return $this->artifactables()->where('category', 'output');
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

    public function clearInputArtifacts(): void
    {
        $this->inputArtifacts()->detach();
        $this->updateRelationCounter('inputArtifacts');
    }

    public function clearOutputArtifacts(): void
    {
        $this->outputArtifacts()->detach();
        $this->updateRelationCounter('outputArtifacts');
    }

    /**
     * Whenever a process state has changed, call this method to check if the task run has completed or has changed
     * state as well
     */
    public function checkProcesses(): static
    {
        LockHelper::acquire($this);

        try {
            $taskProcesses       = $this->taskProcesses()->get();
            $hasRunningProcesses = false;
            $hasStoppedProcesses = false;
            $hasFailedProcesses  = false;
            $hasProcesses        = $taskProcesses->isNotEmpty();

            if (!$this->started_at && $hasProcesses) {
                $this->started_at = now();
            }

            foreach ($taskProcesses as $taskProcess) {
                if ($taskProcess->isStopped()) {
                    $hasStoppedProcesses = true;
                } elseif ($taskProcess->isStatusFailed()) {
                    $hasFailedProcesses = true;
                } elseif (!$taskProcess->isFinished()) {
                    $hasRunningProcesses = true;
                }
            }

            if ($hasRunningProcesses) {
                $this->failed_at    = null;
                $this->stopped_at   = null;
                $this->completed_at = null;
                $this->skipped_at   = null;
            } elseif ($hasFailedProcesses) {
                $this->completed_at = null;
                $this->stopped_at   = null;
                $this->skipped_at   = null;
                if (!$this->failed_at) {
                    $this->failed_at = now();
                }
            } elseif ($hasStoppedProcesses) {
                $this->completed_at = null;
                $this->failed_at    = null;
                $this->skipped_at   = null;
                if (!$this->stopped_at) {
                    $this->stopped_at = now();
                }
            } elseif (!$hasProcesses) {
                $this->failed_at    = null;
                $this->stopped_at   = null;
                $this->completed_at = null;
                if (!$this->skipped_at) {
                    static::logDebug("No processes for task, marking as skipped: $this");
                    $this->skipped_at = now();
                }
            } else {
                $this->failed_at  = null;
                $this->stopped_at = null;
                $this->skipped_at = null;
                if (!$this->completed_at) {
                    $this->completed_at = now();
                }
            }

            return $this;
        } finally {
            LockHelper::release($this);
        }
    }

    /**
     * Get the TaskRunner class instance for the task run
     */
    public function getRunner(): TaskRunnerContract
    {
        return $this->taskDefinition->getRunner()->setTaskRun($this);
    }

    public function refreshUsageFromProcesses(): void
    {
        $this->aggregateChildUsage('taskProcesses');
    }

    /**
     * Get all error log entries for this task run
     * Traverses: TaskRun -> TaskProcesses -> JobDispatches -> AuditRequests -> ErrorLogEntries
     */
    public function getErrorLogEntries()
    {
        // Get all job dispatch IDs for this task run's processes
        $jobDispatchIds = \DB::table('job_dispatchables')
            ->whereIn('model_id', $this->taskProcesses()->pluck('id'))
            ->where('model_type', \App\Models\Task\TaskProcess::class)
            ->pluck('job_dispatch_id');

        // Get error log entries from audit requests associated with those job dispatches
        return \Newms87\Danx\Models\Audit\ErrorLogEntry::whereHas('auditRequest', function ($query) use ($jobDispatchIds) {
            $query->whereIn('id', function ($subquery) use ($jobDispatchIds) {
                $subquery->select('running_audit_request_id')
                    ->from('job_dispatch')
                    ->whereIn('id', $jobDispatchIds)
                    ->whereNotNull('running_audit_request_id');
            });
        })->with(['errorLog', 'auditRequest'])->orderByDesc('created_at')->get();
    }

    public static function booted(): void
    {
        static::saving(function (TaskRun $taskRun) {
            $taskRun->computeStatus();
        });

        static::saved(function (TaskRun $taskRun) {
            if ($taskRun->wasChanged('status')) {
                // If the task run was recently completed, let the service know so we can trigger any events
                if ($taskRun->isCompleted()) {
                    // Complete the taskRun but use a different instance of the model
                    // so we avoid an infinite loop in case the onComplete call triggers a save on the taskRun instance
                    TaskRunnerService::onComplete($taskRun->fresh());
                }

                $taskRun->workflowRun?->checkTaskRuns()->save();

                if ($taskRun->workflowRun?->taskProcessListeners->isNotEmpty()) {
                    foreach ($taskRun->workflowRun->taskProcessListeners as $taskProcessListener) {
                        TaskProcessRunnerService::eventTriggered($taskProcessListener);
                    }
                }
            }

            // Update the workflow run's error count when this task run's error count changes
            if ($taskRun->wasChanged('task_process_error_count')) {
                $taskRun->workflowRun?->updateErrorCount();
            }

            if ($taskRun->wasRecentlyCreated || $taskRun->wasChanged(['status', 'input_artifacts_count', 'output_artifacts_count', 'percent_complete', 'task_process_error_count', 'process_count'])) {
                // If this is a task process job, we want to broadcast the changes immediately to provide a better user experience
                // No need to spin up another job just to broadcast the status
                if (Job::$runningJob) {
                    TaskRunUpdatedEvent::broadcast($taskRun);
                } else {
                    TaskRunUpdatedEvent::dispatch($taskRun);
                }
            }
        });
    }

    public function __toString()
    {
        return "<TaskRun id='$this->id' name='$this->name' status='$this->status' step='$this->step' processes='$this->process_count'>";
    }
}
