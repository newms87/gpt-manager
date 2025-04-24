<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskProcessRunnerService;
use Log;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TaskProcessRepository extends ActionRepository
{
    const int PENDING_PROCESS_TIMEOUT    = 120; // 2 minutes
    const int DISPATCHED_PROCESS_TIMEOUT = 120; // 2 minutes

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
     * Check for timeouts on task processes
     */
    public function checkForTimeout(TaskProcess $taskProcess): bool
    {
        if ($taskProcess->isPending() && $taskProcess->created_at->isBefore(now()->subSeconds(self::PENDING_PROCESS_TIMEOUT))) {
            $this->handleTimeout($taskProcess);

            return true;
        } elseif ($taskProcess->isDispatched() && $taskProcess->created_at->isBefore(now()->subSeconds(self::DISPATCHED_PROCESS_TIMEOUT))) {
            $this->handleTimeout($taskProcess);

            return true;
        } elseif ($taskProcess->isRunning() && $taskProcess->isPastTimeout()) {
            $this->handleTimeout($taskProcess);

            return true;
        }

        return false;
    }

    /**
     * Handle the timeout of a task process.
     * The task process will be restarted automatically if the max_process_retries has not been reached
     */
    public function handleTimeout(TaskProcess $taskProcess): void
    {
        LockHelper::acquire($taskProcess);

        try {
            // Can only be timed out if it is in one of these states
            if (!$taskProcess->isPending() && !$taskProcess->isDispatched() && !$taskProcess->isRunning()) {
                return;
            }

            Log::warning("Task process timed out: $taskProcess");
            $taskProcess->timeout_at = now();
            $taskProcess->save();

            if ($taskProcess->restart_count < $taskProcess->taskRun->taskDefinition->max_process_retries) {
                $this->restartTaskProcess($taskProcess);
            }
        } finally {
            LockHelper::release($taskProcess);
        }
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
