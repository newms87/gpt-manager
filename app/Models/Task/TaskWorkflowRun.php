<?php

namespace App\Models\Task;

use App\Models\Usage\UsageSummary;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskWorkflowRun extends Model implements WorkflowStatesContract
{
    use SoftDeletes, HasWorkflowStatesTrait;

    protected $fillable = [
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
    ];

    public function casts(): array
    {
        return [
            'started_at'   => 'timestamp',
            'stopped_at'   => 'timestamp',
            'completed_at' => 'timestamp',
            'failed_at'    => 'timestamp',
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
    public function targetNodeCanBeRun(TaskWorkflowNode $targetNode): bool
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
     */
    public function getSourceNodeArtifacts(TaskWorkflowNode $targetNode): array
    {
        $artifacts = [];
        foreach($targetNode->connectionsAsTarget as $connectionAsTarget) {
            $sourceNodeTaskRun = $this->taskRuns()->where('task_workflow_node_id', $connectionAsTarget->source_node_id)->first();

            $artifacts = array_merge($artifacts, $sourceNodeTaskRun->outputArtifacts);
        }

        return $artifacts;
    }

    /**
     * Whenever a task run state has changed, call this method to check if the workflow run has completed or has changed
     * state as well
     */
    public function checkTaskRuns(): void
    {
        // If we are already in an end state, we don't need to check the processes
        if (!$this->canContinue()) {
            return;
        }

        $hasRunningTasks = false;

        foreach($this->taskRuns()->get() as $taskRun) {
            // If any process has failed or timed out, the task run has failed (we can stop checking)
            if ($taskRun->isFailed() || $taskRun->isTimeout()) {
                $this->failed_at = now();
                $this->save();

                return;
            } elseif (!$taskRun->isFinished()) {
                $hasRunningTasks = true;
            }
        }

        if (!$hasRunningTasks && !$this->isFailed() && !$this->isStopped()) {
            $this->completed_at = now();
            $this->save();
        }
    }

    public function __toString()
    {
        return "<TaskWorkflowRun id='$this->id' status='$this->status'>";
    }
}
