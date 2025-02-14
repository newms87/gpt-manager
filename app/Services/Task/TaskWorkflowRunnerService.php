<?php

namespace App\Services\Task;

use App\Models\Task\TaskInput;
use App\Models\Task\TaskRun;
use App\Models\Task\TaskWorkflow;
use App\Models\Task\TaskWorkflowNode;
use App\Models\Task\TaskWorkflowRun;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;

class TaskWorkflowRunnerService
{
    use HasDebugLogging;

    /**
     * Start a task workflow run
     */
    public static function start(TaskWorkflow $taskWorkflow, TaskInput $taskInput = null, Collection|array $artifacts = []): TaskWorkflowRun
    {
        static::log("Starting $taskWorkflow");

        $taskWorkflowRun = $taskWorkflow->taskWorkflowRuns()->create([
            'name'       => $taskWorkflow->name,
            'started_at' => now(),
        ]);

        if (empty($taskWorkflow->startingWorkflowNodes)) {
            throw  new ValidationError("Workflow does not have any starting nodes");
        }

        foreach($taskWorkflow->startingWorkflowNodes as $taskWorkflowNode) {
            static::startNode($taskWorkflowRun, $taskWorkflowNode, $taskInput, $artifacts);
        }

        return $taskWorkflowRun;
    }

    /**
     * Start a task run for the given node in the workflow, passing all source node output artifacts
     */
    public static function startNode(TaskWorkflowRun $taskWorkflowRun, TaskWorkflowNode $taskWorkflowNode, TaskInput $taskInput = null, Collection|array $artifacts = []): TaskRun
    {
        static::log("Starting node $taskWorkflowNode");

        // First gather all the artifacts from the target node's source nodes
        $artifacts = collect($artifacts);
        $artifacts = $artifacts->merge($taskWorkflowRun->collectOutputArtifactsFromSourceNodes($taskWorkflowNode));

        // Then run the node
        $taskRun = TaskRunnerService::prepareTaskRun($taskWorkflowNode->taskDefinition, $taskInput, $artifacts);
        $taskRun->taskWorkflowRun()->associate($taskWorkflowRun)->save();
        $taskRun->taskWorkflowNode()->associate($taskWorkflowNode)->save();
        TaskRunnerService::continue($taskRun);

        return $taskRun;
    }

    /**
     * Continue the task run by executing the next set of processes.
     * NOTE: This will not dispatch the next processes if the task run is stopped or failed
     */
    public static function continue(TaskWorkflowRun $taskWorkflowRun): void
    {
        static::log("Continuing $taskWorkflowRun");

        // Always start by acquiring the lock for the task workflow run before checking if it can continue
        // NOTE: This prevents allowing the TaskRun to continue if there was a race condition on failing/stopping the TaskRun
        LockHelper::acquire($taskWorkflowRun);

        if ($taskWorkflowRun->isFinished()) {
            static::log("TaskRun is $taskWorkflowRun->status, skipping execution");

            return;
        }

        try {
            throw new \Exception("Implement this, check for the next task runs to dispatch");
        } finally {
            LockHelper::release($taskWorkflowRun);
        }
    }

    /**
     * Resume the task run. This will resume all task processes that were stopped
     */
    public static function resume(TaskWorkflowRun $taskWorkflowRun): void
    {
        static::log("Resuming $taskWorkflowRun");

        LockHelper::acquire($taskWorkflowRun);

        try {
            if (!$taskWorkflowRun->isStopped() && !$taskWorkflowRun->isPending()) {
                static::log("TaskWorkflowRun is not stopped, skipping resume");

                return;
            }

            $taskWorkflowRun->stopped_at = null;
            $taskWorkflowRun->save();

            // Resume all the stopped task runs
            foreach($taskWorkflowRun->taskRuns()->whereNotNull('stopped_at')->get() as $taskRun) {
                TaskRunnerService::resume($taskRun);
            }
        } finally {
            LockHelper::release($taskWorkflowRun);
        }

        static::continue($taskWorkflowRun);
    }

    /**
     * Stop the task workflow run. This will stop all tasks and prevent any further execution
     */
    public static function stop(TaskWorkflowRun $taskWorkflowRun): void
    {
        static::log("Stopping $taskWorkflowRun");

        LockHelper::acquire($taskWorkflowRun);

        try {
            if ($taskWorkflowRun->isStopped()) {
                static::log("TaskWorkflowRun is already stopped");

                return;
            }

            $taskWorkflowRun->stopped_at = now();
            $taskWorkflowRun->save();

            foreach($taskWorkflowRun->taskRuns as $taskRun) {
                TaskRunnerService::stop($taskRun);
            }
        } finally {
            LockHelper::release($taskWorkflowRun);
        }
    }

    /**
     * When a task run has completed, check to see if there are connections for the given node and execute them
     */
    public static function taskRunComplete(TaskRun $taskRun): void
    {
        static::log("Received TaskRun Completed: $taskRun");

        $taskWorkflowRun = $taskRun->taskWorkflowRun;

        LockHelper::acquire($taskWorkflowRun);

        try {
            // If there are no dependent connections, we can skip this step
            foreach($taskRun->taskWorkflowNode->connectionsAsSource as $taskWorkflowConnection) {
                if ($taskWorkflowRun->targetNodeCanBeRun($taskWorkflowConnection->targetNode)) {
                    static::startNode($taskWorkflowRun, $taskWorkflowConnection->targetNode);
                } else {
                    static::log("Waiting for sources before running target $taskWorkflowConnection->targetNode");
                }
            }
        } finally {
            LockHelper::release($taskWorkflowRun);
        }
    }
}
