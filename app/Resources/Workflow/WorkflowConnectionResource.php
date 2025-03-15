<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowConnection;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowConnectionResource extends ActionResource
{
    public static function data(WorkflowConnection $workflowConnection): array
    {
        return [
            'id'                 => $workflowConnection->id,
            'name'               => $workflowConnection->name,
            'source_output_port' => $workflowConnection->source_output_port,
            'target_input_port'  => $workflowConnection->target_input_port,
            'source_node_id'     => $workflowConnection->source_node_id,
            'target_node_id'     => $workflowConnection->target_node_id,
            'sourceNode'         => fn($fields) => WorkflowNodeResource::data($workflowConnection->sourceNode, $fields),
            'targetNode'         => fn($fields) => WorkflowNodeResource::data($workflowConnection->targetNode, $fields),
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
