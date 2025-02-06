<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Services\Task\TaskRunnerService;
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

    public function resumeTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskRunnerService::resumeProcess($taskProcess);

        return $taskProcess;
    }

    public function stopTaskProcess(TaskProcess $taskProcess): TaskProcess
    {
        TaskRunnerService::stopProcess($taskProcess);

        return $taskProcess;
    }
}
