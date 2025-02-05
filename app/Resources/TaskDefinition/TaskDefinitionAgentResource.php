<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskDefinitionAgent;
use App\Resources\Agent\AgentResource;
use App\Resources\Prompt\PromptSchemaFragmentResource;
use App\Resources\Prompt\PromptSchemaResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskDefinitionAgentResource extends ActionResource
{
    public static function data(TaskDefinitionAgent $taskAgent): array
    {
        return [
            'id'            => $taskAgent->id,
            'include_text'  => (bool)$taskAgent->include_text,
            'include_files' => (bool)$taskAgent->include_files,
            'include_data'  => (bool)$taskAgent->include_data,
            'created_at'    => $taskAgent->created_at,
            'updated_at'    => $taskAgent->updated_at,

            'agent'                => fn($fields) => AgentResource::make($taskAgent->agent, $fields),
            'inputSchema'          => fn($fields) => PromptSchemaResource::make($taskAgent->inputSchema, $fields),
            'inputSchemaFragment'  => fn($fields) => PromptSchemaFragmentResource::make($taskAgent->inputSchemaFragment, $fields),
            'outputSchema'         => fn($fields) => PromptSchemaResource::make($taskAgent->outputSchema, $fields),
            'outputSchemaFragment' => fn($fields) => PromptSchemaFragmentResource::make($taskAgent->outputSchemaFragment, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*'     => true,
            'agent' => [
                'name'  => true,
                'model' => true,
            ],
        ]);
    }
}
