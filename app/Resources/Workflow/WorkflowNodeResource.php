<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowNode;
use App\Resources\TaskDefinition\TaskDefinitionResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowNodeResource extends ActionResource
{
    public static function data(WorkflowNode $workflowNode): array
    {
        return [
            'id'                 => $workflowNode->id,
            'name'               => $workflowNode->name,
            'settings'           => $workflowNode->settings,
            'params'             => $workflowNode->params,
            'updated_at'         => $workflowNode->updated_at,
            'task_definition_id' => $workflowNode->task_definition_id,

            'taskDefinition'      => fn($fields) => TaskDefinitionResource::make($workflowNode->taskDefinition, $fields),
            'connectionsAsTarget' => fn($fields) => WorkflowConnectionResource::collection($workflowNode->connectionsAsTarget, $fields),
            'connectionsAsSource' => fn($fields) => WorkflowConnectionResource::collection($workflowNode->connectionsAsSource, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'taskDefinition' => true,
        ]);
    }
}
