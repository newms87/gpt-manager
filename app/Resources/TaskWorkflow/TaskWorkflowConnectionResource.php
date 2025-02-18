<?php

namespace App\Resources\TaskWorkflow;

use App\Models\Task\TaskWorkflowConnection;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskWorkflowConnectionResource extends ActionResource
{
    public static function data(TaskWorkflowConnection $taskWorkflowConnection): array
    {
        return [
            'id'                 => $taskWorkflowConnection->id,
            'name'               => $taskWorkflowConnection->name,
            'source_output_port' => $taskWorkflowConnection->source_output_port,
            'target_input_port'  => $taskWorkflowConnection->target_input_port,
            'source_node_id'     => $taskWorkflowConnection->source_node_id,
            'target_node_id'     => $taskWorkflowConnection->target_node_id,
            'sourceNode'         => fn($fields) => TaskWorkflowNodeResource::data($taskWorkflowConnection->sourceNode, $fields),
            'targetNode'         => fn($fields) => TaskWorkflowNodeResource::data($taskWorkflowConnection->targetNode, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'sourceNode' => ['id' => true],
            'targetNode' => ['id' => true],
        ]);
    }
}
