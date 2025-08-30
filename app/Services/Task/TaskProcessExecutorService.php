<?php

namespace App\Services\Task;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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

            $taskProcess = $this->findNextTaskProcessForQuery($workflowRun->taskProcesses());
        } finally {
            LockHelper::release($workflowRun);
        }

        if ($taskProcess) {
            $this->executeTaskProcess($taskProcess);
        } else {
            static::log("No available task processes for WorkflowRun {$workflowRun->id}");
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

            $taskProcess = $this->findNextTaskProcessForQuery($taskRun->taskProcesses());

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
     * Find the next available task process from a base query
     */
    protected function findNextTaskProcessForQuery(Builder|Relation $taskProcessesQuery): ?TaskProcess
    {
        // Check for the existence of the readyToRun Laravel scope on the $taskProcessesQuery builder
        if (!method_exists($taskProcessesQuery->getModel(), 'scopeReadyToRun')) {
            throw new Exception("The provided query does not have the readyToRun scope");
        }

        // Use chunk to process one at a time until we find one with available slots
        $foundProcess = null;
        $taskProcessesQuery->readyToRun()->chunk(1, function ($processes) use (&$foundProcess) {
            $process       = $processes->first();
            $taskQueueType = $process->taskRun->taskDefinition->taskQueueType;

            if (!$taskQueueType || $taskQueueType->hasAvailableSlots()) {
                // If the lock cannot be acquired, it means another worker is already processing this
                if (!LockHelper::get('next-to-run-' . $process->id, 10)) {
                    return true; // Continue to next chunk
                }
                // Re-check within the
                $foundProcess = $process;
            }

            return $foundProcess === null; // if not found, continue to next. Otherwise, stop processing and return the found process
        });

        return $foundProcess;
    }

    /**
     * Execute a task process
     */
    protected function executeTaskProcess(TaskProcess $taskProcess): void
    {
        static::log("Found available task process: $taskProcess");

        // If this is a timed out or incomplete process, restart it first
        if ($taskProcess->isStatusTimeout() || $taskProcess->isStatusIncomplete()) {
            static::log("Restarting {$taskProcess->status} process: $taskProcess");
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

        foreach($runningProcesses as $process) {
            if ($process->isPastTimeout()) {
                static::log("Marking timed out process as timeout: $process");
                $process->update([
                    'timeout_at' => now(),
                    'status'     => WorkflowStatesContract::STATUS_TIMEOUT,
                ]);
            }
        }
    }
}
