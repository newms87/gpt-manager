<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskDefinition;
use App\Resources\Agent\AgentResource;
use App\Resources\Schema\SchemaAssociationResource;
use App\Resources\Schema\SchemaDefinitionResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskDefinitionResource extends ActionResource
{
    public static function data(TaskDefinition $taskDefinition): array
    {
        return [
            'id'                     => $taskDefinition->id,
            'name'                   => $taskDefinition->name,
            'description'            => $taskDefinition->description,
            'prompt'                 => $taskDefinition->prompt,
            'is_trigger'             => $taskDefinition->isTrigger(),
            'task_runner_name'       => $taskDefinition->task_runner_name,
            'task_runner_config'     => $taskDefinition->task_runner_config,
            'response_format'        => $taskDefinition->response_format,
            'input_artifact_mode'    => $taskDefinition->input_artifact_mode,
            'input_artifact_levels'  => $taskDefinition->input_artifact_levels,
            'output_artifact_mode'   => $taskDefinition->output_artifact_mode,
            'output_artifact_levels' => $taskDefinition->output_artifact_levels,
            'timeout_after_seconds'  => $taskDefinition->timeout_after_seconds,
            'meta'                   => $taskDefinition->meta,
            'task_run_count'         => $taskDefinition->task_run_count,
            'created_at'             => $taskDefinition->created_at,
            'updated_at'             => $taskDefinition->updated_at,

            'agent'                       => fn($fields) => $taskDefinition->agent ? AgentResource::make($taskDefinition->agent, $fields) : null,
            'taskInputs'                  => fn($fields) => TaskInputResource::collection($taskDefinition->taskInputs, $fields),
            'taskRuns'                    => fn($fields) => TaskRunResource::collection($taskDefinition->taskRuns, $fields),
            'taskArtifactFiltersAsTarget' => fn($fields) => TaskArtifactFilterResource::collection($taskDefinition->taskArtifactFiltersAsTarget, $fields),
            'taskArtifactFiltersAsSource' => fn($fields) => TaskArtifactFilterResource::collection($taskDefinition->taskArtifactFiltersAsSource, $fields),
            'schemaDefinition'            => fn($fields) => SchemaDefinitionResource::make($taskDefinition->schemaDefinition, $fields),
            'schemaAssociations'          => fn($fields) => SchemaAssociationResource::collection($taskDefinition->schemaAssociations, $fields),
            'taskDefinitionDirectives'    => fn($fields) => TaskDefinitionDirectiveResource::collection($taskDefinition->taskDefinitionDirectives, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'agent'                       => true,
            'taskInputs'                  => true,
            'taskArtifactFiltersAsTarget' => true,
            'schemaDefinition'            => true,
            'schemaAssociations'          => true,
            'taskDefinitionDirectives'    => true,
        ]);
    }
}
