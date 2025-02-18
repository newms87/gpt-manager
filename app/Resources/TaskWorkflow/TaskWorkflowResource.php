<?php

namespace App\Resources\TaskWorkflow;

use App\Models\Task\TaskWorkflow;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskWorkflowResource extends ActionResource
{
    public static function data(TaskWorkflow $taskWorkflow): array
    {
        return [
            'id'          => $taskWorkflow->id,
            'name'        => $taskWorkflow->name,
            'description' => $taskWorkflow->description,
            'created_at'  => $taskWorkflow->created_at,
            'updated_at'  => $taskWorkflow->updated_at,

            'nodes'       => fn($fields) => TaskWorkflowNodeResource::collection($taskWorkflow->taskWorkflowNodes, $fields),
            'connections' => fn($fields) => TaskWorkflowConnectionResource::collection($taskWorkflow->taskWorkflowConnections, $fields),
            'runs'        => fn($fields) => TaskWorkflowRunResource::collection($taskWorkflow->taskWorkflowRuns, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'nodes'       => true,
            'connections' => true,
            'runs'        => true,
        ]);
    }
}
