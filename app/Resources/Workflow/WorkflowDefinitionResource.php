<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowDefinitionResource extends ActionResource
{
    public static function data(WorkflowDefinition $workflowDefinition): array
    {
        return [
            'id'          => $workflowDefinition->id,
            'name'        => $workflowDefinition->name,
            'description' => $workflowDefinition->description,
            'max_workers' => $workflowDefinition->max_workers,
            'created_at'  => $workflowDefinition->created_at,
            'updated_at'  => $workflowDefinition->updated_at,
            'team_id'     => $workflowDefinition->team_id,

            'nodes'       => fn($fields) => WorkflowNodeResource::collection($workflowDefinition->workflowNodes, $fields),
            'connections' => fn($fields) => WorkflowConnectionResource::collection($workflowDefinition->workflowConnections, $fields),
            'runs'        => fn($fields) => WorkflowRunResource::collection($workflowDefinition->workflowRuns, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'nodes'       => [
                'taskDefinition' => true,
            ],
            'connections' => true,
        ]);
    }
}
