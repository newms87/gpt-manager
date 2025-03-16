<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskProcessRunnerService;
use Log;
use Newms87\Danx\Repositories\ActionRepository;

class TaskProcessRepository extends ActionRepository
{
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
        if ($taskProcess->isPastTimeout()) {
            Log::warning("Task process timed out: $taskProcess");
            $taskProcess->timeout_at = now();
            $taskProcess->save();

            return true;
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
