<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskProcessRunnerService;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TaskProcessRepository extends ActionRepository
{
    use HasDebugLogging;

    const int PENDING_PROCESS_TIMEOUT = 120; // 2 minutes

    public static string $model = TaskProcess::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'restart' => $this->restartTaskProcess($model),
            'resume' => $this->resumeTaskProcess($model),
            'stop' => $this->stopTaskProcess($model),
        };
    }

    /**
     * Check for timeouts on all running (or about to run) task processes
     */
    public function checkForTimeouts(): void
    {
        $statuses         = [WorkflowStatesContract::STATUS_RUNNING, WorkflowStatesContract::STATUS_PENDING];
        $runningProcesses = TaskProcess::whereIn('status', $statuses)->get();

        static::log("Checking for timeouts on task processes: " . $runningProcesses->count());

        foreach($runningProcesses as $taskProcess) {
            $this->checkForTimeout($taskProcess);
        }
    }

    /**
     * Check for timeouts on task processes
     */
    public function checkForTimeout(TaskProcess $taskProcess): bool
    {
        LockHelper::acquire($taskProcess);

        try {
            if ($taskProcess->isStatusRunning() && $taskProcess->isPastTimeout()) {
                static::log("\t$taskProcess->id: Running timeout");
                TaskProcessRunnerService::handleTimeout($taskProcess);

                return true;
            }
        } finally {
            LockHelper::release($taskProcess);
        }

        return false;
    }

    public function restartTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskProcessRunnerService::restart($taskProcess);

        return $taskProcess;
    }

    /**
     * Resume the task process. This will resume the task process if it was stopped
     */
    public function resumeTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskProcessRunnerService::resume($taskProcess);

        return $taskProcess;
    }

    /**
     * Stop the task process. This will stop the task process and prevent any further execution
     */
    public function stopTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskProcessRunnerService::stop($taskProcess);

        return $taskProcess;
    }
}
