<?php

namespace App\Models\Workflow;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Models\Usage\UsageSummary;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Workflow\WorkflowRunnerService;
use App\Traits\HasDebugLogging;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowRun extends Model implements WorkflowStatesContract, AuditableContract
{
    use SoftDeletes, ActionModelTrait, AuditableTrait, HasWorkflowStatesTrait, HasDebugLogging;

    protected $fillable = [
        'name',
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
    ];

    public function casts(): array
    {
        return [
            'started_at'   => 'datetime:Y-m-d H:i:s.v',
            'stopped_at'   => 'datetime:Y-m-d H:i:s.v',
            'completed_at' => 'datetime:Y-m-d H:i:s.v',
            'failed_at'    => 'datetime:Y-m-d H:i:s.v',
        ];
    }

    public function workflowApiInvocation(): HasOne|WorkflowApiInvocation
    {
        return $this->hasOne(WorkflowApiInvocation::class);
    }

    public function workflowDefinition(): BelongsTo|WorkflowDefinition
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function taskRuns(): HasMany|TaskRun
    {
        return $this->hasMany(TaskRun::class);
    }

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object');
    }

    public function taskProcessListeners(): MorphMany
    {
        return $this->morphMany(TaskProcessListener::class, 'event');
    }

    /**
     * Checks if the given target node is ready to be run by checking if all of its source nodes have completed running
     */
    public function targetNodeReadyToRun(WorkflowNode $targetNode): bool
    {
        // For all the target's source nodes, we want to check if they have all completed running in this workflow run.
        // If so, then we can run the target node
        foreach($targetNode->connectionsAsTarget as $connectionAsTarget) {
            // Check if this workflow run has a task run for the source node that has completed.
            // If not, the target node is not ready to be executed
            if ($this->taskRuns()->where('workflow_node_id', $connectionAsTarget->source_node_id)->where('status', WorkflowStatesContract::STATUS_COMPLETED)->doesntExist()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all the artifacts from the source nodes of the given target node
     * @return Collection<Artifact>
     */
    public function collectInputArtifactsForNode(WorkflowNode $node): Collection
    {
        return $this->taskRuns()->where('workflow_node_id', $node->id)->first()?->inputArtifacts()->get() ?? collect();
    }

    /**
     * Get all the artifacts from the source nodes of the given target node
     * @return Collection<Artifact>
     */
    public function collectOutputArtifactsForNode(WorkflowNode $node): Collection
    {
        return $this->taskRuns()->where('workflow_node_id', $node->id)->first()?->outputArtifacts()->get() ?? collect();
    }

    /**
     * Collect all the final output artifacts from the workflow run
     * @return Collection|Artifact[]
     */
    public function collectFinalOutputArtifacts(): Collection
    {
        $outputArtifacts = collect();

        foreach($this->taskRuns as $taskRun) {
            // Only consider task runs that do not have any target nodes that depend on them
            if ($taskRun->workflowNode->connectionsAsSource()->exists()) {
                continue;
            }

            $outputArtifacts = $outputArtifacts->merge($taskRun->outputArtifacts()->get());
        }

        return $outputArtifacts;
    }

    /**
     * Whenever a task run state has changed, call this method to check if the workflow run has completed or has changed
     * state as well
     */
    public function checkTaskRuns(): static
    {
        $hasRunningTasks = false;
        $hasStoppedTasks = false;
        $hasFailedTasks  = false;

        // Make sure to set the flag to indicate that all required tasks have been run so the workflow can know when it is completed
        if ($this->hasRunAllTasks()) {
            static::log("All tasks have been run, setting flag");
            $this->has_run_all_tasks = true;
        } else {
            $this->has_run_all_tasks = false;
            $this->completed_at      = null;
        }

        foreach($this->taskRuns()->get() as $taskRun) {
            if ($taskRun->isStopped()) {
                $hasStoppedTasks = true;
            } elseif ($taskRun->isFailed()) {
                // If any task has failed or timed out, the task run has failed (we can stop checking)
                $hasFailedTasks = true;
            } elseif (!$taskRun->isFinished()) {
                $hasRunningTasks = true;
            }
        }

        if ($hasRunningTasks) {
            $this->failed_at    = null;
            $this->stopped_at   = null;
            $this->completed_at = null;
        } elseif ($hasFailedTasks) {
            $this->completed_at = null;
            $this->stopped_at   = null;
            if (!$this->failed_at) {
                $this->failed_at = now();
            }
        } elseif ($hasStoppedTasks) {
            $this->completed_at = null;
            $this->failed_at    = null;
            if (!$this->stopped_at) {
                $this->stopped_at = now();
            }
        } else {
            $this->failed_at  = null;
            $this->stopped_at = null;
            if (!$this->completed_at && $this->has_run_all_tasks) {
                $this->completed_at = now();
            }
        }

        return $this;
    }

    public function hasRunAllTasks(): bool
    {
        return $this->taskRuns()->whereIn('status', [WorkflowStatesContract::STATUS_PENDING, WorkflowStatesContract::STATUS_RUNNING])->doesntExist();
    }

    public static function booted(): void
    {
        static::saving(function (WorkflowRun $workflowRun) {
            if ($workflowRun->isDirty('has_run_all_tasks')) {
                $workflowRun->checkTaskRuns();
            }

            $workflowRun->computeStatus();
        });

        static::saved(function (WorkflowRun $workflowRun) {
            // If the workflow run was recently completed, let the service know so we can trigger any events
            if ($workflowRun->wasChanged('status')) {
                if ($workflowRun->isCompleted()) {
                    WorkflowRunnerService::onComplete($workflowRun);
                }

                if ($workflowRun->taskProcessListeners->isNotEmpty()) {
                    foreach($workflowRun->taskProcessListeners as $taskProcessListener) {
                        TaskProcessRunnerService::eventTriggered($taskProcessListener);
                    }
                }

                WorkflowRunUpdatedEvent::dispatch($workflowRun);
            }
        });
    }

    public function __toString()
    {
        return "<WorkflowRun id='$this->id' status='$this->status'>";
    }
}
