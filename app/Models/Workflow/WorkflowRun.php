<?php

namespace App\Models\Workflow;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskProcessListener;
use App\Models\Task\TaskRun;
use App\Models\Traits\HasUsageTracking;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Workflow\WorkflowRunnerService;
use App\Traits\HasDebugLogging;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowRun extends Model implements WorkflowStatesContract, AuditableContract
{
    use HasFactory, SoftDeletes, ActionModelTrait, AuditableTrait, HasWorkflowStatesTrait, HasDebugLogging, HasUsageTracking;

    protected $fillable = [
        'name',
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'active_workers_count',
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

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    public function jobDispatches(): MorphToMany
    {
        return $this->morphToMany(JobDispatch::class, 'model', 'job_dispatchables')->orderByDesc('id');
    }

    /**
     * Calculate the current number of active workers for this workflow
     */
    public function activeWorkers(): MorphToMany|JobDispatch
    {
        return $this->jobDispatches()->whereIn('status', [JobDispatch::STATUS_RUNNING, JobDispatch::STATUS_PENDING]);
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
            $sourceTaskRun = $this->taskRuns()->where('workflow_node_id', $connectionAsTarget->source_node_id)->first();
            if (!$sourceTaskRun?->isCompleted()) {
                // Bad data handling - in case something went wrong and the data was corrupted, we can fix it here
                if (!$connectionAsTarget->sourceNode) {
                    static::log('Source node did not exist: ' . $connectionAsTarget->source_node_id);
                    $this->cleanCorruptedConnections();
                    continue;
                }
                static::log("Waiting for $connectionAsTarget->sourceNode to complete: " . ($sourceTaskRun ?: "(No Task Run)"));

                return false;
            }
        }

        return true;
    }

    public function cleanCorruptedConnections()
    {
        static::log("Clean corrupted connections for $this");

        $validNodeIds = $this->workflowDefinition->workflowNodes()->pluck('id');

        $corruptedConnections = WorkflowConnection::where('workflow_definition_id', $this->workflowDefinition->id)
            ->where(function ($query) use ($validNodeIds) {
                $query->whereNotIn('source_node_id', $validNodeIds)
                    ->orWhereNotIn('target_node_id', $validNodeIds);
            })
            ->get();

        if ($corruptedConnections->isNotEmpty()) {
            static::log("Found " . $corruptedConnections->count() . " corrupted connections to delete");
            $corruptedConnections->each->delete();
        }
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

    public function taskProcessesReadyToRun(): Builder
    {
        $workflowRun = $this;

        return TaskProcess::whereHas('taskRun', function (Builder $q) use ($workflowRun) {
            $q->where('workflow_run_id', $workflowRun->id);
        })
            ->where(function (Builder $q) {
                // Pending processes
                $q->where('status', WorkflowStatesContract::STATUS_PENDING)
                    // Or incomplete/timeout processes that can be retried
                    ->orWhere(function (Builder $retryQuery) {
                        $retryQuery->whereIn('status', [WorkflowStatesContract::STATUS_INCOMPLETE, WorkflowStatesContract::STATUS_TIMEOUT])
                            ->whereHas('taskRun.taskDefinition', function (Builder $taskDefQuery) {
                                $taskDefQuery->whereColumn('task_processes.restart_count', '<', 'task_definitions.max_process_retries');
                            });
                    });
            });
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

    /**
     * Update the cached active workers count
     */
    public function updateActiveWorkersCount(): void
    {
        $this->update(['active_workers_count' => $this->activeWorkers()->count()]);
    }

    public function refreshUsageFromTaskRuns(): void
    {
        $this->aggregateChildUsage('taskRuns');
    }

    /**
     * Calculate the progress percentage of this workflow run
     * Progress is based on completed, failed, and skipped task runs vs total workflow nodes
     * Also includes unreachable nodes that will never run due to skipped/failed dependencies
     */
    public function calculateProgress(): float
    {
        // If the workflow run itself is marked as completed/failed/stopped, it's 100% done
        if ($this->completed_at || $this->failed_at || $this->stopped_at) {
            return 100.0;
        }

        $totalNodes = $this->workflowDefinition->workflowNodes()->count();

        $finishedTaskRuns = $this->taskRuns()
            ->whereIn('status', [
                WorkflowStatesContract::STATUS_COMPLETED,
                WorkflowStatesContract::STATUS_FAILED,
                WorkflowStatesContract::STATUS_SKIPPED,
            ])
            ->count();

        // If there are no workflow nodes defined, fall back to task run based calculation
        if ($totalNodes === 0) {
            $totalTaskRuns = $this->taskRuns()->count();
            if ($totalTaskRuns === 0) {
                return 0.0;
            }

            return round(($finishedTaskRuns / $totalTaskRuns) * 100);
        }

        // Find nodes that are unreachable due to failed/skipped dependencies
        $unreachableNodesCount = $this->findUnreachableNodes();

        $effectivelyCompleted = $finishedTaskRuns + $unreachableNodesCount;

        return round(($effectivelyCompleted / $totalNodes) * 100);
    }

    /**
     * Find workflow nodes that are unreachable due to failed/skipped dependencies
     * These nodes will never get TaskRuns created because their upstream dependencies failed/skipped
     */
    private function findUnreachableNodes(): int
    {
        // Get all workflow nodes for this workflow
        $allNodes = $this->workflowDefinition->workflowNodes()->get();

        // Get nodes that have TaskRuns (reachable nodes)
        $nodesWithTaskRuns = $this->taskRuns()->with('workflowNode')->get()->pluck('workflowNode')->keyBy('id');

        // Find nodes that don't have TaskRuns yet
        $nodesWithoutTaskRuns = $allNodes->whereNotIn('id', $nodesWithTaskRuns->keys());

        if ($nodesWithoutTaskRuns->isEmpty()) {
            return 0;
        }

        // Get TaskRuns that are failed or skipped (blocking nodes)
        $blockingTaskRuns = $this->taskRuns()
            ->whereIn('status', [
                WorkflowStatesContract::STATUS_FAILED,
                WorkflowStatesContract::STATUS_SKIPPED,
            ])
            ->with('workflowNode')
            ->get();

        // If there are no failed/skipped tasks, no nodes are unreachable due to blocking
        if ($blockingTaskRuns->isEmpty()) {
            return 0;
        }

        $blockingNodeIds  = $blockingTaskRuns->pluck('workflowNode.id')->unique();
        $unreachableNodes = collect();

        // For each node without a TaskRun, check if it's unreachable due to blocking dependencies
        foreach($nodesWithoutTaskRuns as $node) {
            if ($this->isNodeUnreachableDueToBlockingDependencies($node, $blockingNodeIds, $allNodes)) {
                $unreachableNodes->push($node);
            }
        }

        return $unreachableNodes->count();
    }

    /**
     * Check if a node is unreachable due to blocking dependencies
     * A node is unreachable if any of its upstream dependencies (directly or indirectly) failed/skipped
     */
    private function isNodeUnreachableDueToBlockingDependencies(WorkflowNode $node, Collection $blockingNodeIds, Collection $allNodes): bool
    {
        $visited = collect();
        $toCheck = collect([$node->id]);

        while($toCheck->isNotEmpty()) {
            $currentNodeId = $toCheck->shift();

            if ($visited->contains($currentNodeId)) {
                continue;
            }

            $visited->push($currentNodeId);

            // If this node is directly blocked, the target is unreachable
            if ($blockingNodeIds->contains($currentNodeId)) {
                return true;
            }

            // Get all direct dependencies (source nodes) of the current node
            $currentNode = $allNodes->firstWhere('id', $currentNodeId);
            if (!$currentNode) {
                continue;
            }

            $sourceConnections = WorkflowConnection::where('workflow_definition_id', $this->workflowDefinition->id)
                ->where('target_node_id', $currentNodeId)
                ->get();

            // Add all source nodes to check queue
            foreach($sourceConnections as $connection) {
                if (!$visited->contains($connection->source_node_id)) {
                    $toCheck->push($connection->source_node_id);
                }
            }
        }

        return false;
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
                };
            }

            if ($workflowRun->wasChanged(['status', 'active_workers_count', 'name'])) {
                WorkflowRunUpdatedEvent::broadcast($workflowRun);
            }
        });
    }

    public function __toString()
    {
        return "<WorkflowRun id='$this->id' status='$this->status'>";
    }
}
