<?php

namespace App\Repositories;

use App\Models\Task\TaskRun;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class TaskRunRepository extends ActionRepository
{
    public static string $model = TaskRun::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createTaskRun($data),
            'resume' => $this->resumeTaskRun($model),
            'restart' => $this->restartTaskRun($model),
            'stop' => $this->stopTaskRun($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createTaskRun(array $data): TaskRun
    {
        $taskDefinition = team()->taskDefinitions()->find($data['task_definition_id'] ?? null);

        if (!$taskDefinition) {
            throw new ValidationError('Failed to run task: Task definition was not found');
        }

        $taskInput = $taskDefinition->taskInputs()->find($data['task_input_id'] ?? null);

        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);

        // Associate the task input to the task run
        if ($taskInput) {
            $taskRun->taskInput()->associate($taskInput)->save();
            $taskRun->syncInputArtifacts([$taskInput->toArtifact()]);
        }

        TaskRunnerService::continue($taskRun);

        return $taskRun;
    }

    public function resumeTaskRun(TaskRun $taskRun): TaskRun
    {
        TaskRunnerService::resume($taskRun);

        return $taskRun;
    }

    public function restartTaskRun(TaskRun $taskRun): TaskRun
    {
        TaskRunnerService::restart($taskRun);

        return $taskRun;
    }

    public function stopTaskRun(TaskRun $taskRun): TaskRun
    {
        TaskRunnerService::stop($taskRun);

        return $taskRun;
    }


}
