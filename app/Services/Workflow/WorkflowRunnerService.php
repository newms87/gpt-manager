<?php

namespace App\Services\Workflow;

use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskRunnerService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;

class WorkflowRunnerService
{
    use HasDebugLogging;

    /**
     * Start a workflow run
     */
    public static function start(WorkflowDefinition $workflowDefinition, WorkflowInput $workflowInput = null, Collection|array $artifacts = []): WorkflowRun
    {
        static::log("Starting $workflowDefinition");

        if ($workflowDefinition->startingWorkflowNodes->isEmpty()) {
            throw  new ValidationError("Workflow does not have any starting nodes");
        }

        // Create the workflow run
        $workflowRun = $workflowDefinition->workflowRuns()->create([
            'name'       => $workflowDefinition->name,
            'started_at' => now_ms(),
        ]);

        // Add the workflow input to the list of artifacts
        if ($workflowInput) {
            $artifacts[] = app(WorkflowInputToArtifactMapper::class)->setWorkflowInput($workflowInput)->map();
        }

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

        // First gather all the artifacts from the target node's source nodes
        $artifacts = collect($artifacts)->merge($workflowRun->collectOutputArtifactsFromSourceNodes($workflowNode));

        // Then run the node
        $taskRun = TaskRunnerService::prepareTaskRun($workflowNode->taskDefinition, $artifacts);
        $taskRun->workflowRun()->associate($workflowRun)->save();
        $taskRun->workflowNode()->associate($workflowNode)->save();
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

            $workflowRun->stopped_at = now_ms();
            $workflowRun->save();

            foreach($workflowRun->taskRuns as $taskRun) {
                TaskRunnerService::stop($taskRun);
            }
        } finally {
            LockHelper::release($workflowRun);
        }
    }

    /**
     * When a task run has completed, check to see if there are connections for the given node and execute them
     */
    public static function taskRunComplete(TaskRun $taskRun): void
    {
        static::log("Received TaskRun Completed: $taskRun");

        $workflowRun = $taskRun->workflowRun;

        LockHelper::acquire($workflowRun);

        try {
            // For every connection on the workflow node, start the target node if it is ready to run
            foreach($taskRun->workflowNode->connectionsAsSource as $workflowConnection) {
                $targetNode = $workflowConnection->targetNode;

                // If this workflow node has already been started, we don't want to start it again
                if ($workflowRun->taskRuns()->where('workflow_node_id', $targetNode->id)->exists()) {
                    static::log("Target node has already been started $targetNode");
                    continue;
                }

                // If this node is ready to run, start it
                if ($workflowRun->targetNodeReadyToRun($targetNode)) {
                    static::startNode($workflowRun, $targetNode);
                } else {
                    static::log("Waiting for sources before running target $targetNode");
                }
            }

            // Make sure to set the flag to indicate that all required tasks have been run so the workflow can know when it is completed
            if ($workflowRun->taskRuns()->whereIn('status', [WorkflowStatesContract::STATUS_PENDING, WorkflowStatesContract::STATUS_RUNNING])->doesntExist()) {
                static::log("All tasks have been run, setting flag");
                $workflowRun->has_run_all_tasks = true;
                $workflowRun->save();
            }
        } finally {
            LockHelper::release($workflowRun);
        }
    }
}
