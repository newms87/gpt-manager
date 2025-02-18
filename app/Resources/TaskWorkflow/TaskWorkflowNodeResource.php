<?php

namespace App\Resources\TaskWorkflow;

use App\Models\Task\TaskWorkflowNode;
use App\Resources\TaskDefinition\TaskDefinitionResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskWorkflowNodeResource extends ActionResource
{
    public static function data(TaskWorkflowNode $taskWorkflowNode): array
    {
        return [
            'id'         => $taskWorkflowNode->id,
            'name'       => $taskWorkflowNode->name,
            'settings'   => $taskWorkflowNode->settings,
            'params'     => $taskWorkflowNode->params,
            'updated_at' => $taskWorkflowNode->updated_at,

            'taskDefinition' => fn($fields) => TaskDefinitionResource::data($taskWorkflowNode->taskDefinition, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'taskDefinition' => true,
        ]);
    }
}
