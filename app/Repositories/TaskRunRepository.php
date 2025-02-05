<?php

namespace App\Repositories;

use App\Models\Task\TaskRun;
use App\Services\Task\TaskInputToArtifactMapper;
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

        $artifact = (new TaskInputToArtifactMapper)->setTaskInput($taskInput)->map();

        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition, [$artifact]);

        $taskRun->taskInput()->associate($taskInput)->save();

        return $taskRun;
    }
}
