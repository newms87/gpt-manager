<?php

namespace App\Models\Task;

use App\Models\Usage\UsageSummary;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Runners\TaskRunnerContract;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskRun extends Model implements AuditableContract, WorkflowStatesContract
{
    use HasFactory, AuditableTrait, ActionModelTrait, HasRelationCountersTrait, HasWorkflowStatesTrait, SoftDeletes;

    protected $fillable = [
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'task_input_id',
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

    public function workflowRun(): BelongsTo|WorkflowDefinition
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function workflowNode(): BelongsTo|WorkflowNode
    {
        return $this->belongsTo(WorkflowNode::class);
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

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object');
    }

    /**
     * Whenever a process state has changed, call this method to check if the task run has completed or has changed
     * state as well
     */
    public function checkProcesses(): static
    {
        $hasRunningProcesses = false;
        $hasStoppedProcesses = false;
        $hasFailedProcesses  = false;

        foreach($this->taskProcesses()->get() as $taskProcess) {
            if ($taskProcess->isStopped()) {
                $hasStoppedProcesses = true;
            } elseif ($taskProcess->isFailed() || $taskProcess->isTimeout()) {
                // If any process has failed or timed out, the task run has failed (we can stop checking)
                $hasFailedProcesses = true;
            } elseif (!$taskProcess->isFinished()) {
                $hasRunningProcesses = true;
            }
        }


        if ($hasRunningProcesses) {
            $this->failed_at    = null;
            $this->stopped_at   = null;
            $this->completed_at = null;
        } elseif ($hasFailedProcesses) {
            $this->completed_at = null;
            $this->stopped_at   = null;
            if (!$this->failed_at) {
                $this->failed_at = now();
            }
        } elseif ($hasStoppedProcesses) {
            $this->completed_at = null;
            $this->failed_at    = null;
            if (!$this->stopped_at) {
                $this->stopped_at = now();
            }
        } else {
            $this->failed_at  = null;
            $this->stopped_at = null;
            if (!$this->completed_at) {
                $this->completed_at = now();
            }
        }

        return $this;
    }

    /**
     * Get the TaskRunner class instance for the task run
     */
    public function getRunner(): TaskRunnerContract
    {
        return $this->taskDefinition->getRunner()->setTaskRun($this);
    }

    public static function booted(): void
    {
        static::saving(function (TaskRun $taskRun) {
            $taskRun->computeStatus();
        });

        static::saved(function (TaskRun $taskRun) {
            if ($taskRun->wasChanged('status')) {
                $taskRun->workflowRun?->checkTaskRuns()->save();

                // If the task run was recently completed, let the service know so we can trigger any events
                if ($taskRun->isCompleted()) {
                    TaskRunnerService::onComplete($taskRun);
                }

                if ($taskRun->workflowRun?->taskProcessListeners->isNotEmpty()) {
                    foreach($taskRun->workflowRun->taskProcessListeners as $taskProcessListener) {
                        TaskProcessRunnerService::eventTriggered($taskProcessListener);
                    }
                }
            }
        });
    }

    public function __toString()
    {
        return "<TaskRun id='$this->id' name='$this->name' status='$this->status' step='$this->step' processes='$this->process_count'>";
    }
}
