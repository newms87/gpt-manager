<?php

namespace App\Models\Task;

use App\Models\Usage\UsageSummary;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Newms87\Danx\Traits\ActionModelTrait;

class TaskWorkflowRun extends Model implements WorkflowStatesContract
{
    use SoftDeletes, ActionModelTrait, HasWorkflowStatesTrait;

    protected $fillable = [
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

    public function taskWorkflow(): BelongsTo|TaskWorkflow
    {
        return $this->belongsTo(TaskWorkflow::class);
    }

    public function taskRuns(): HasMany|TaskRun
    {
        return $this->hasMany(TaskRun::class);
    }

    public function usageSummary(): MorphOne
    {
        return $this->morphOne(UsageSummary::class, 'object');
    }

    /**
     * Checks if the given target node is ready to be run by checking if all of its source nodes have completed running
     */
    public function targetNodeReadyToRun(TaskWorkflowNode $targetNode): bool
    {
        // For all the target's source nodes, we want to check if they have all completed running in this workflow run.
        // If so, then we can run the target node
        foreach($targetNode->connectionsAsTarget as $connectionAsTarget) {
            // Check if this workflow run has a task run for the source node that has completed.
            // If not, the target node is not ready to be executed
            if ($this->taskRuns()->where('task_workflow_node_id', $connectionAsTarget->source_node_id)->where('status', WorkflowStatesContract::STATUS_COMPLETED)->doesntExist()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all the artifacts from the source nodes of the given target node
     * @return Collection<Artifact>
     */
    public function collectOutputArtifactsFromSourceNodes(TaskWorkflowNode $targetNode): Collection
    {
        $artifacts = collect();

        // Loop through all the source nodes of the target node to gather the output artifacts of each one
        foreach($targetNode->connectionsAsTarget as $connectionAsTarget) {
            $outputArtifacts = $this->collectOutputArtifactsForNode($connectionAsTarget->sourceNode);
            $artifacts       = $artifacts->merge($outputArtifacts);
        }

        return $artifacts;
    }

    /**
     * Get all the artifacts from the source nodes of the given target node
     * @return Collection<Artifact>
     */
    public function collectInputArtifactsForNode(TaskWorkflowNode $node): Collection
    {
        return $this->taskRuns()->where('task_workflow_node_id', $node->id)->first()->inputArtifacts()->get() ?? collect();
    }

    /**
     * Get all the artifacts from the source nodes of the given target node
     * @return Collection<Artifact>
     */
    public function collectOutputArtifactsForNode(TaskWorkflowNode $node): Collection
    {
        return $this->taskRuns()->where('task_workflow_node_id', $node->id)->first()->outputArtifacts()->get() ?? collect();
    }

    /**
     * Whenever a task run state has changed, call this method to check if the workflow run has completed or has changed
     * state as well
     */
    public function checkTaskRuns(): void
    {
        $hasRunningTasks = false;
        $hasStoppedTasks = false;
        $hasFailedTasks  = false;

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

        if ($hasFailedTasks) {
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
        } elseif ($hasRunningTasks) {
            $this->failed_at    = null;
            $this->stopped_at   = null;
            $this->completed_at = null;
        } else {
            $this->failed_at  = null;
            $this->stopped_at = null;
            if (!$this->completed_at && $this->has_run_all_tasks) {
                $this->completed_at = now();
            }
        }

        $this->save();
    }

    public static function booted(): void
    {
        static::saving(function (TaskWorkflowRun $taskWorkflowRun) {
            $taskWorkflowRun->computeStatus();
        });

        static::saved(function (TaskWorkflowRun $taskWorkflowRun) {
            if ($taskWorkflowRun->isDirty('has_run_all_tasks')) {
                $taskWorkflowRun->checkTaskRuns();
            }
        });
    }

    public function __toString()
    {
        return "<TaskWorkflowRun id='$this->id' status='$this->status'>";
    }
}
