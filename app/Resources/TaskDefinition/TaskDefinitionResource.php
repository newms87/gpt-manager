<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskDefinition;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskDefinitionResource extends ActionResource
{
    public static function data(TaskDefinition $taskDefinition): array
    {
        return [
            'id'                    => $taskDefinition->id,
            'name'                  => $taskDefinition->name,
            'description'           => $taskDefinition->description,
            'task_runner_class'     => $taskDefinition->task_runner_class,
            'task_runner_config'    => $taskDefinition->task_runner_config,
            'artifact_split_mode'   => $taskDefinition->artifact_split_mode,
            'timeout_after_seconds' => $taskDefinition->timeout_after_seconds,
            'task_run_count'        => $taskDefinition->task_run_count,
            'task_agent_count'      => $taskDefinition->task_agent_count,
            'created_at'            => $taskDefinition->created_at,
            'updated_at'            => $taskDefinition->updated_at,

            'taskAgents'                  => fn($fields) => TaskDefinitionAgentResource::collection($taskDefinition->definitionAgents, $fields),
            'taskInputs'                  => fn($fields) => TaskInputResource::collection($taskDefinition->taskInputs, $fields),
            'taskRuns'                    => fn($fields) => TaskRunResource::collection($taskDefinition->taskRuns, $fields),
            'taskArtifactFiltersAsSource' => fn($fields) => TaskArtifactFilterResource::collection($taskDefinition->taskArtifactFiltersAsSource, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'taskAgents'                  => [
                'agent'                   => true,
                'inputSchemaAssociations' => true,
                'outputSchemaAssociation' => true,
            ],
            'taskInputs'                  => true,
            'taskArtifactFiltersAsSource' => true,
        ]);
    }
}
