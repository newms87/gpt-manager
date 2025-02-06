<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskRunnerService;
use Log;
use Newms87\Danx\Repositories\ActionRepository;

class TaskProcessRepository extends ActionRepository
{
    public static string $model = TaskProcess::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
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

    /**
     * Resume the task process. This will resume the task process if it was stopped
     */
    public function resumeTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskRunnerService::resumeProcess($taskProcess);

        return $taskProcess;
    }

    /**
     * Stop the task process. This will stop the task process and prevent any further execution
     */
    public function stopTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskRunnerService::stopProcess($taskProcess);

        return $taskProcess;
    }
}
