<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskProcessRunnerService;
use App\Traits\HasDebugLogging;
use Log;
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
     * Check for timeouts on task processes
     */
    public function checkForTimeout(TaskProcess $taskProcess): bool
    {
        static::log("Checking for timeout on task process: $taskProcess");

        // If a task is in pending or dispatched state and hasn't been modified for 2 minutes, it is considered timed out
        if (($taskProcess->isPending() || $taskProcess->isDispatched()) && $taskProcess->updated_at->isBefore(now()->subSeconds(self::PENDING_PROCESS_TIMEOUT))) {
            static::log("\tPending / Dispatch timeout");
            $this->handleTimeout($taskProcess);

            return true;
        } elseif ($taskProcess->isRunning() && $taskProcess->isPastTimeout()) {
            static::log("\tRunning timeout");
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
