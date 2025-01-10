<?php

namespace App\Resources\Agent;

use App\Models\Agent\Agent;
use App\Resources\Prompt\AgentPromptDirectiveResource;
use App\Resources\Prompt\PromptSchemaResource;
use App\Resources\Workflow\WorkflowAssignmentResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class AgentResource extends ActionResource
{
    public static function data(Agent $agent): array
    {
        return [
            'id'                     => $agent->id,
            'knowledge_name'         => $agent->knowledge?->name,
            'name'                   => $agent->name,
            'description'            => $agent->description,
            'api'                    => $agent->api,
            'model'                  => $agent->model,
            'temperature'            => $agent->temperature,
            'tools'                  => $agent->tools ?: [],
            'response_format'        => $agent->response_format,
            'save_response_to_db'    => $agent->save_response_to_db,
            'enable_message_sources' => $agent->enable_message_sources,
            'retry_count'            => $agent->retry_count,
            'threads_count'          => $agent->threads_count,
            'assignments_count'      => $agent->assignments_count,
            'created_at'             => $agent->created_at,
            'updated_at'             => $agent->updated_at,

            'responseSchema'         => fn($fields) => PromptSchemaResource::make($agent->responseSchema, $fields),
            'response_sub_selection' => fn() => $agent->response_sub_selection,
            'directives'             => fn($fields) => AgentPromptDirectiveResource::collection($agent->directives->load('directive'), $fields),
            'threads'                => fn($fields) => ThreadResource::collection($agent->threads()->orderByDesc('updated_at')->with('sortedMessages.storedFiles.transcodes')->limit(20)->get(), $fields),
            'assignments'            => fn($fields) => WorkflowAssignmentResource::collection($agent->assignments()->with('workflowJob.workflow')->limit(20)->get(), $fields),
        ];
    }

    public static function details(Model $model): array
    {
        return static::make($model, [
            '*'           => true,
            'directives'  => ['directive' => true],
            'threads'     => ['messages' => ['files' => ['transcodes' => true]]],
            'assignments' => ['workflowJob' => ['workflow' => true]],
        ]);
    }
}
