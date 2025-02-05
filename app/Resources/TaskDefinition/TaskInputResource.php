<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskInput;
use App\Resources\Workflow\WorkflowInputResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskInputResource extends ActionResource
{
    public static function data(TaskInput $taskInput): array
    {
        return [
            'id'             => $taskInput->id,
            'task_run_count' => $taskInput->task_run_count,

            'workflowInput'  => WorkflowInputResource::make($taskInput->workflowInput),
            'taskDefinition' => fn($fields) => TaskDefinitionResource::make($taskInput->taskDefinition, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*' => true,
        ]);
    }
}
