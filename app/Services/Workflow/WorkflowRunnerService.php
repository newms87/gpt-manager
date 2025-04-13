<?php

namespace App\Services\Workflow;

use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\TaskRunnerService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Helpers\ModelHelper;

class WorkflowRunnerService
{
    use HasDebugLogging;

    /**
     * Start a workflow run
     */
    public static function start(WorkflowDefinition $workflowDefinition, Collection|array $artifacts = []): WorkflowRun
    {
        static::log("Starting $workflowDefinition");

        if ($workflowDefinition->startingWorkflowNodes->isEmpty()) {
            throw  new ValidationError("Workflow does not have any starting nodes");
        }

        // Create the workflow run
        $firstArtifact = $artifacts[0] ?? null;
        $name          = $workflowDefinition->name . ($firstArtifact?->name ? ': ' . $firstArtifact->name : '');
        $workflowRun   = $workflowDefinition->workflowRuns()->make([
            'name'       => $name,
            'started_at' => now_ms(),
        ]);

        $workflowRun->name = ModelHelper::getNextModelName($workflowRun);
        $workflowRun->save();

        // Start all the starting nodes
        foreach($workflowDefinition->startingWorkflowNodes as $workflowNode) {
            static::startNode($workflowRun, $workflowNode, $artifacts);
        }

        return $workflowRun;
    }

    /**
     * Start a task run for the given node in the workflow, passing all source node output artifacts
     */
    public static function startNode(WorkflowRun $workflowRun, WorkflowNode $workflowNode, Collection|array $artifacts = []): TaskRun
    {
        static::log("Starting node $workflowNode");

        // Then run the node
        $taskRun = TaskRunnerService::prepareTaskRun($workflowNode->taskDefinition);
        $taskRun->workflowRun()->associate($workflowRun)->save();
        $taskRun->workflowNode()->associate($workflowNode)->save();

        // Sync artifacts from the workflow source nodes
        TaskRunnerService::syncInputArtifactsFromWorkflowSourceNodes($taskRun);

        // Sync any additional artifacts that did not come directly from the workflow source nodes
        $taskRun->syncInputArtifacts($artifacts);

        // Prepare the task processes for the task run
        TaskRunnerService::prepareTaskProcesses($taskRun);

        // Start the task run
        TaskRunnerService::continue($taskRun);

        return $taskRun;
    }

    /**
     * Continue the task run by executing the next set of processes.
     * NOTE: This will not dispatch the next processes if the task run is stopped or failed
     */
    public static function continue(WorkflowRun $workflowRun): void
    {
        static::log("Continuing $workflowRun");

        // Always start by acquiring the lock for the workflow run before checking if it can continue
        // NOTE: This prevents allowing the WorkflowRun to continue if there was a race condition on failing/stopping the WorkflowRun
        LockHelper::acquire($workflowRun);

        if ($workflowRun->isFinished()) {
            static::log("WorkflowRun is $workflowRun->status, skipping execution");

            return;
        }

        try {
            throw new Exception("Implement this, check for the next task runs to dispatch");
        } finally {
            LockHelper::release($workflowRun);
        }
    }

    /**
     * Resume the workflow run. This will resume all task runs that were stopped
     */
    public static function resume(WorkflowRun $workflowRun): void
    {
        static::log("Resuming $workflowRun");

        LockHelper::acquire($workflowRun);

        try {
            if (!$workflowRun->isStopped() && !$workflowRun->isPending()) {
                static::log("WorkflowRun is not stopped, skipping resume");

                return;
            }

            $workflowRun->stopped_at = null;
            $workflowRun->save();

            // Resume all the stopped task runs
            foreach($workflowRun->taskRuns()->whereNotNull('stopped_at')->get() as $taskRun) {
                TaskRunnerService::resume($taskRun);
            }
        } finally {
            LockHelper::release($workflowRun);
        }

        static::continue($workflowRun);
    }

    /**
     * Stop the workflow run. This will stop all task runs and prevent any further execution
     */
    public static function stop(WorkflowRun $workflowRun): void
    {
        static::log("Stopping $workflowRun");

        LockHelper::acquire($workflowRun);

        try {
            if ($workflowRun->isStopped()) {
                static::log("Workflow run is already stopped");

                return;
            }

            foreach($workflowRun->taskRuns as $taskRun) {
                TaskRunnerService::stop($taskRun);
            }

            // Double-check our state in case we're out of sync
            $workflowRun->checkTaskRuns()->computeStatus()->save();
        } finally {
            LockHelper::release($workflowRun);
        }
    }

    /**
     * Handle the completion of a workflow run
     */
    public static function onComplete(WorkflowRun $workflowRun): void
    {
        static::log("Completed $workflowRun");
    }

    /**
     * When a task run has completed, check to see if there are connections for the given node and execute them
     */
    public static function onNodeComplete(WorkflowRun $workflowRun, WorkflowNode $workflowNode): void
    {
        static::log("Node Completed $workflowNode");

        LockHelper::acquire($workflowRun);

        try {
            // For every connection on the workflow node, start the target node if it is ready to run
            foreach($workflowNode->connectionsAsSource as $workflowConnection) {
                $targetNode = $workflowConnection->targetNode;

                // If this workflow node has already been started, we don't want to start it again
                if ($workflowRun->taskRuns()->where('workflow_node_id', $targetNode->id)->exists()) {
                    static::log("Target node has already been started $targetNode");
                    continue;
                }

                // If this node is ready to run, start it
                if ($workflowRun->targetNodeReadyToRun($targetNode)) {
                    WorkflowRunnerService::startNode($workflowRun, $targetNode);
                } else {
                    static::log("Waiting for sources before running target $targetNode");
                }
            }

            $workflowRun->checkTaskRuns()->save();
        } finally {
            LockHelper::release($workflowRun);
        }
    }
}
