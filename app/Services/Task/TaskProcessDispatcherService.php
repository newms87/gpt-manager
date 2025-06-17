<?php

namespace App\Services\Task;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Helpers\LockHelper;

class TaskProcessDispatcherService
{
    use HasDebugLogging;

    /**
     * Dispatch available processes for a single task run
     */
    public static function dispatchForTaskRun(TaskRun $taskRun): void
    {
        static::log("Dispatching processes for TaskRun $taskRun");

        // If this task run is part of a workflow, dispatch at the workflow level
        // to ensure proper prioritization across all tasks
        if ($taskRun->workflow_run_id) {
            static::log("TaskRun is part of workflow, dispatching at workflow level");
            static::dispatchForWorkflowRun($taskRun->workflowRun);

            return;
        }

        // Lock at the task run level to prevent concurrent dispatching
        LockHelper::acquire($taskRun, 60);

        try {
            $availableSlots = static::calculateAvailableSlotsForTaskRun($taskRun);

            if ($availableSlots <= 0) {
                static::log("No available worker slots");

                return;
            }

            // Process task processes
            static::processTaskRunProcesses($taskRun, $availableSlots);
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Dispatch available processes for all task runs in a workflow
     */
    public static function dispatchForWorkflowRun(WorkflowRun $workflowRun): void
    {
        static::log("Dispatching processes for WorkflowRun $workflowRun");

        // Lock at the workflow level to prevent concurrent dispatching
        LockHelper::acquire($workflowRun, 60);

        try {
            $availableWorkflowSlots = static::calculateAvailableSlotsForWorkflow($workflowRun);

            if ($availableWorkflowSlots <= 0) {
                static::log("No available workflow worker slots");

                return;
            }

            // Get all pending processes across all task runs, ordered by created_at
            $pendingProcesses = TaskProcess::whereHas('taskRun', function ($query) use ($workflowRun) {
                $query->where('workflow_run_id', $workflowRun->id);
            })
                ->where('status', WorkflowStatesContract::STATUS_PENDING)
                ->orderBy('created_at')
                ->get();

            static::log("Found " . $pendingProcesses->count() . " pending processes across workflow");

            if ($pendingProcesses->isEmpty()) {
                return;
            }

            $dispatchedCount  = 0;
            $taskRunSlotCache = [];

            // Process each pending process in order, checking task-level limits
            foreach($pendingProcesses as $taskProcess) {
                if ($dispatchedCount >= $availableWorkflowSlots) {
                    break;
                }

                $taskRun   = $taskProcess->taskRun;
                $taskRunId = $taskRun->id;

                // Cache task-level slot calculations to avoid recalculating for same task run
                if (!isset($taskRunSlotCache[$taskRunId])) {
                    $taskRunSlotCache[$taskRunId] = static::calculateAvailableSlotsForTaskRun($taskRun);
                }

                if ($taskRunSlotCache[$taskRunId] <= 0) {
                    static::log("No available slots for TaskRun $taskRun, skipping");
                    continue;
                }

                // Handle timeout or dispatch pending process
                if (static::handleTaskProcess($taskProcess)) {
                    $dispatchedCount++;
                    $taskRunSlotCache[$taskRunId]--; // Decrement available slots for this task run
                }
            }

            static::log("Dispatched $dispatchedCount processes for WorkflowRun $workflowRun");
        } finally {
            LockHelper::release($workflowRun);
        }
    }

    /**
     * Calculate available slots for a workflow
     */
    private static function calculateAvailableSlotsForWorkflow(WorkflowRun $workflowRun): int
    {
        $workflowMaxWorkers          = $workflowRun->workflowDefinition->max_workers ?? 20;
        $runningWorkflowWorkersCount = static::countRunningWorkersForWorkflow($workflowRun);

        static::log("WorkflowRun $workflowRun has $runningWorkflowWorkersCount/$workflowMaxWorkers workers running");

        return $workflowMaxWorkers - $runningWorkflowWorkersCount;
    }

    /**
     * Calculate available slots for a task run (only considering task-level limits)
     */
    private static function calculateAvailableSlotsForTaskRun(TaskRun $taskRun): int
    {
        $taskMaxWorkers          = $taskRun->taskDefinition->max_workers ?? 10;
        $runningTaskWorkersCount = static::countRunningWorkersForTaskRun($taskRun);

        static::log("TaskRun $taskRun has $runningTaskWorkersCount/$taskMaxWorkers workers running");

        return $taskMaxWorkers - $runningTaskWorkersCount;
    }

    /**
     * Count running workers for a task run
     */
    private static function countRunningWorkersForTaskRun(TaskRun $taskRun): int
    {
        return $taskRun->taskProcesses()
            ->whereIn('status', [
                WorkflowStatesContract::STATUS_DISPATCHED,
                WorkflowStatesContract::STATUS_RUNNING,
            ])
            ->count();
    }

    /**
     * Count running workers for a workflow
     */
    private static function countRunningWorkersForWorkflow(WorkflowRun $workflowRun): int
    {
        return $workflowRun->taskRuns()
            ->join('task_processes', 'task_runs.id', '=', 'task_processes.task_run_id')
            ->whereIn('task_processes.status', [
                WorkflowStatesContract::STATUS_DISPATCHED,
                WorkflowStatesContract::STATUS_RUNNING,
            ])
            ->count();
    }

    /**
     * Process task processes for a task run
     */
    private static function processTaskRunProcesses(TaskRun $taskRun, int $limit): void
    {
        $processedCount = 0;

        foreach($taskRun->taskProcesses as $taskProcess) {
            if ($processedCount >= $limit) {
                break;
            }

            if ($taskProcess->isCompleted()) {
                static::log("TaskProcess already Completed. Skipping dispatch: $taskProcess");
                continue;
            }

            // Handle timeout or dispatch pending process
            if (static::handleTaskProcess($taskProcess)) {
                $processedCount++;
            }
        }
    }

    /**
     * Handle a task process - either dispatch if pending or handle timeout
     *
     * @return bool True if a process was dispatched (either directly or via restart), false otherwise
     */
    private static function handleTaskProcess(TaskProcess $taskProcess): bool
    {
        if ($taskProcess->isPastTimeout()) {
            static::log("TaskProcess $taskProcess timed out");

            return TaskProcessRunnerService::handleTimeout($taskProcess);
        } elseif ($taskProcess->isStatusPending()) {
            TaskProcessRunnerService::dispatch($taskProcess);

            return true;
        }

        return false;
    }
}
