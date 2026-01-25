<?php

namespace App\Repositories;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;
use App\Resources\TaskDefinition\TaskProcessResource;
use App\Services\Task\TaskProcessRunnerService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TaskProcessRepository extends ActionRepository
{
    use HasDebugLogging;

    const int PENDING_PROCESS_TIMEOUT = 120; // 2 minutes

    public static string $model = TaskProcess::class;

    public function listQuery(): Builder
    {
        $query = parent::listQuery()
            ->orderByDesc('id');

        // Support withTrashed query parameter to include soft-deleted records
        if (request()->boolean('withTrashed')) {
            $query->withTrashed();
        }

        return $query;
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        if ($model === null) {
            throw new ValidationError('Task process not found');
        }

        return match ($action) {
            'restart' => $this->restartTaskProcess($model),
            'resume'  => $this->resumeTaskProcess($model),
            'stop'    => $this->stopTaskProcess($model),
        };
    }

    /**
     * Check for timeouts on all running (or about to run) task processes
     */
    public function checkForTimeouts(): void
    {
        $statuses         = [WorkflowStatesContract::STATUS_RUNNING, WorkflowStatesContract::STATUS_PENDING];
        $runningProcesses = TaskProcess::whereIn('status', $statuses)->get();

        static::logDebug('Checking for timeouts on task processes: ' . $runningProcesses->count());

        foreach ($runningProcesses as $taskProcess) {
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
                static::logDebug("\t$taskProcess->id: Running timeout");
                TaskProcessRunnerService::handleTimeout($taskProcess);

                return true;
            }
        } finally {
            LockHelper::release($taskProcess);
        }

        return false;
    }

    public function restartTaskProcess(TaskProcess $taskProcess): array
    {
        $newTaskProcess = TaskProcessRunnerService::restart($taskProcess);

        return TaskProcessResource::make($newTaskProcess);
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

    /**
     * Get filter field options for task processes
     */
    public function fieldOptions(?array $filter = []): array
    {
        // Build base query with team scoping via task_runs and task_definitions
        $query = TaskProcess::query()
            ->join('task_runs', 'task_processes.task_run_id', '=', 'task_runs.id')
            ->join('task_definitions', 'task_runs.task_definition_id', '=', 'task_definitions.id')
            ->where('task_definitions.team_id', team()->id);

        // Apply additional filters if provided (e.g., task_run_id)
        if (!empty($filter['task_run_id'])) {
            $query->where('task_processes.task_run_id', $filter['task_run_id']);
        }

        // Get distinct operation values
        $operations = (clone $query)
            ->whereNotNull('task_processes.operation')
            ->distinct()
            ->pluck('task_processes.operation')
            ->sort()
            ->values()
            ->toArray();

        // Get distinct status values
        $statuses = (clone $query)
            ->whereNotNull('task_processes.status')
            ->distinct()
            ->pluck('task_processes.status')
            ->sort()
            ->values()
            ->toArray();

        return [
            'operation' => $operations,
            'status'    => $statuses,
        ];
    }
}
