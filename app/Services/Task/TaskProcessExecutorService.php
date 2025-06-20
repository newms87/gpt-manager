<?php

namespace App\Services\Task;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Helpers\LockHelper;

class TaskProcessExecutorService
{
    use HasDebugLogging;

    /**
     * Run the next available task process for a workflow run
     */
    public function runNextTaskProcessForWorkflowRun(WorkflowRun $workflowRun): void
    {
        LockHelper::acquire($workflowRun, 60);

        try {
            // Check for timed out running processes first
            $this->checkForTimedOutProcesses($workflowRun);

            $taskProcess = $this->findNextTaskProcessForWorkflow($workflowRun);

            if ($taskProcess) {
                $this->executeTaskProcess($taskProcess);
            } else {
                static::log("No available task processes for WorkflowRun {$workflowRun->id}");
            }
        } finally {
            LockHelper::release($workflowRun);
        }
    }

    /**
     * Run the next available task process for a task run
     */
    public function runNextTaskProcessForTaskRun(TaskRun $taskRun): void
    {
        LockHelper::acquire($taskRun, 60);

        try {
            // Check for timed out running processes first
            $this->checkForTimedOutProcesses($taskRun);

            $taskProcess = $this->findNextTaskProcessForTaskRun($taskRun);

            if ($taskProcess) {
                $this->executeTaskProcess($taskProcess);
            } else {
                static::log("No available task processes for TaskRun {$taskRun->id}");
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Find the next available task process for a workflow
     */
    protected function findNextTaskProcessForWorkflow(WorkflowRun $workflowRun): ?TaskProcess
    {
        // Query for eligible processes one at a time to avoid loading thousands
        $baseQuery = TaskProcess::whereHas('taskRun', function (Builder $q) use ($workflowRun) {
            $q->where('workflow_run_id', $workflowRun->id);
        })
            ->where(function (Builder $q) {
                // Pending processes
                $q->where('status', WorkflowStatesContract::STATUS_PENDING)
                    // Or timed out processes that can be retried
                    ->orWhere(function (Builder $timeoutQuery) {
                        $timeoutQuery->where('status', WorkflowStatesContract::STATUS_TIMEOUT)
                            ->whereHas('taskRun.taskDefinition', function (Builder $taskDefQuery) {
                                $taskDefQuery->whereColumn('task_processes.restart_count', '<', 'task_definitions.max_process_retries');
                            });
                    });
            })
            ->with(['taskRun.taskDefinition.taskQueueType'])
            ->orderBy('created_at');

        // Use chunk to process one at a time until we find one with available slots
        $foundProcess = null;
        $baseQuery->chunk(1, function ($processes) use (&$foundProcess) {
            $process = $processes->first();
            $taskQueueType = $process->taskRun->taskDefinition->taskQueueType;

            if (!$taskQueueType || $taskQueueType->hasAvailableSlots()) {
                $foundProcess = $process;
                return false; // Stop chunking
            }

            return true; // Continue to next chunk
        });

        return $foundProcess;
    }

    /**
     * Find the next available task process for a task run
     */
    protected function findNextTaskProcessForTaskRun(TaskRun $taskRun): ?TaskProcess
    {
        // Check if the task run's queue type has available slots
        $taskQueueType = $taskRun->taskDefinition->taskQueueType;
        if ($taskQueueType && !$taskQueueType->hasAvailableSlots()) {
            static::log("Queue type '{$taskQueueType->name}' has no available slots for TaskRun {$taskRun->id}");
            return null;
        }

        return $taskRun->taskProcesses()
            ->where(function (Builder $q) {
                // Pending processes
                $q->where('status', WorkflowStatesContract::STATUS_PENDING)
                    // Or timed out processes that can be retried
                    ->orWhere(function (Builder $timeoutQuery) {
                        $timeoutQuery->where('status', WorkflowStatesContract::STATUS_TIMEOUT)
                            ->whereHas('taskRun.taskDefinition', function (Builder $taskDefQuery) {
                                $taskDefQuery->whereColumn('task_processes.restart_count', '<', 'task_definitions.max_process_retries');
                            });
                    });
            })
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Execute a task process
     */
    protected function executeTaskProcess(TaskProcess $taskProcess): void
    {
        static::log("Found pending task process: $taskProcess");

        // If this is a timed out process, restart it first
        if ($taskProcess->isStatusTimeout()) {
            static::log("Restarting timed out process: $taskProcess");
            TaskProcessRunnerService::restart($taskProcess);
            return;
        }

        // Execute the task process
        TaskProcessRunnerService::run($taskProcess);
    }


    /**
     * Check for and mark timed out running processes
     */
    protected function checkForTimedOutProcesses(WorkflowRun|TaskRun $context): void
    {
        if ($context instanceof WorkflowRun) {
            $runningProcesses = TaskProcess::whereHas('taskRun', function (Builder $q) use ($context) {
                $q->where('workflow_run_id', $context->id);
            })->where('status', WorkflowStatesContract::STATUS_RUNNING)->get();
        } else {
            $runningProcesses = $context->taskProcesses()
                ->where('status', WorkflowStatesContract::STATUS_RUNNING)
                ->get();
        }

        foreach ($runningProcesses as $process) {
            if ($process->isPastTimeout()) {
                static::log("Marking timed out process as timeout: $process");
                $process->update([
                    'timeout_at' => now(),
                    'status' => WorkflowStatesContract::STATUS_TIMEOUT,
                ]);
            }
        }
    }
}
