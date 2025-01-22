<?php

namespace App\Services\Task;

use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use Illuminate\Database\Eloquent\Collection;

class TaskRunner
{
    protected TaskDefinition $taskDefinition;
    protected array          $artifacts;

    public function __construct(TaskDefinition $taskDefinition, array|Collection $artifacts = [])
    {
        $this->taskDefinition = $taskDefinition;
        $this->artifacts      = $artifacts;
    }

    public static function makeRunnerForDefinition(TaskDefinition $taskDefinition, array|Collection $artifacts = []): static
    {
        return new $taskDefinition->task_service($taskDefinition, $artifacts);
    }

    public function run()
    {
        $taskRun = $this->prepareTaskRun();

    }

    public function prepareTaskRun()
    {
        $taskRun = $this->taskDefinition->taskRuns()->create([
            'status' => TaskProcess::STATUS_PENDING,
        ]);

        $this->prepareTaskProcesses($taskRun);

        return $taskRun;
    }

    public function prepareTaskProcesses(TaskRun $taskRun): array
    {
        $taskProcesses = [];

        $taskProcesses[] = $taskRun->taskProcesses()->create([
            'status' => TaskProcess::STATUS_PENDING,
        ]);

        $taskRun->taskProcesses()->saveMany($taskProcesses);

        return $taskProcesses;
    }
}
