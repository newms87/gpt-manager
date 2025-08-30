<?php

namespace App\Services\Task;

use App\Jobs\TaskProcessJob;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowRun;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Helpers\LockHelper;

class TaskProcessDispatcherService
{
    use HasDebugLogging;

    /**
     * Dispatch available workers for a single task run
     */
    public static function dispatchForTaskRun(TaskRun $taskRun): void
    {
        static::log("Dispatching workers for TaskRun $taskRun");

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
                static::log("No available worker slots for TaskRun $taskRun");

                return;
            }

            // Dispatch generic jobs up to the available slots
            static::log("Dispatching $availableSlots TaskProcessJobs for TaskRun {$taskRun->id}");
            for($i = 0; $i < $availableSlots; $i++) {
                $job = new TaskProcessJob($taskRun);
                $job->onQueue('task-process');
                $job->dispatch();
            }
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Dispatch available workers for all task runs in a workflow
     */
    public static function dispatchForWorkflowRun(WorkflowRun $workflowRun): void
    {
        static::log("Dispatching workers for $workflowRun");

        // Lock at the workflow level to prevent concurrent dispatching
        LockHelper::acquire($workflowRun, 60);

        try {
            $availableSlots = static::calculateAvailableSlotsForWorkflow($workflowRun);

            if ($availableSlots <= 0) {
                static::log("No available worker slots");

                return;
            }

            $pendingProcessesCount = $workflowRun->taskProcesses()->readyToRun()->count();

            static::log("Workers: $availableSlots available slots / $pendingProcessesCount pending processes");

            $workerCount = min($availableSlots, $pendingProcessesCount);

            if ($workerCount <= 0) {
                static::log("No workers required");

                return;
            }

            static::log("Dispatching $availableSlots workers");

            // Dispatch generic jobs up to the available slots for the workflow
            // The jobs will internally check queue type limits when selecting workers for task processes
            $jobIds = [];
            for($i = 0; $i < $availableSlots; $i++) {
                $job = new TaskProcessJob(null, $workflowRun);
                $job->onQueue('task-process')->dispatch();
                static::log("Dispatched worker " . ($i + 1) . "/{$availableSlots}");
                $jobIds[] = $job->getJobDispatch()->id;
            }

            // Update the cached active workers count
            $workflowRun->jobDispatches()->syncWithoutDetaching($jobIds);
            $workflowRun->updateActiveWorkersCount();
        } finally {
            LockHelper::release($workflowRun);
        }
    }


    /**
     * Calculate available slots for a workflow
     */
    private static function calculateAvailableSlotsForWorkflow(WorkflowRun $workflowRun): int
    {
        $workflowMaxWorkers = $workflowRun->workflowDefinition->max_workers ?? 20;

        static::log("WorkflowRun $workflowRun has $workflowRun->active_workers_count/$workflowMaxWorkers workers running");

        return $workflowMaxWorkers - $workflowRun->active_workers_count;
    }

    /**
     * Calculate available slots for a task run (using queue type limits)
     */
    private static function calculateAvailableSlotsForTaskRun(TaskRun $taskRun): int
    {
        $taskQueueType = $taskRun->taskDefinition->taskQueueType;
        if (!$taskQueueType) {
            static::log("TaskRun $taskRun has no queue type, defaulting to 10 available slots");

            return 10;
        }

        $availableSlots = $taskQueueType->getAvailableSlots();
        static::log("TaskRun $taskRun queue type '{$taskQueueType->name}' has $availableSlots available slots");

        return $availableSlots;
    }


}
