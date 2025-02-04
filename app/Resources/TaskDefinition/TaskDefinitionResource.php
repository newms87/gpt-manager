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
            'id'                     => $taskDefinition->id,
            'name'                   => $taskDefinition->name,
            'description'            => $taskDefinition->description,
            'task_runner_class'      => $taskDefinition->task_runner_class,
            'input_grouping'         => $taskDefinition->input_grouping,
            'input_group_chunk_size' => $taskDefinition->input_group_chunk_size,
            'timeout_after_seconds'  => $taskDefinition->timeout_after_seconds,
            'task_run_count'         => $taskDefinition->task_run_count,
            'task_agent_count'       => $taskDefinition->task_agent_count,
            'created_at'             => $taskDefinition->created_at,
            'updated_at'             => $taskDefinition->updated_at,

            'taskAgents' => fn($fields) => TaskDefinitionAgentResource::collection($taskDefinition->definitionAgents, $fields),
            'taskRuns'   => fn($fields) => TaskRunResource::collection($taskDefinition->taskRuns, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*'          => true,
            'taskAgents' => [
                'inputSchema'          => true,
                'inputSchemaFragment'  => true,
                'outputSchema'         => true,
                'outputSchemaFragment' => true,
            ],
            'taskRuns'   => ['*' => true],
        ]);
    }
}
