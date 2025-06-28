<?php

namespace App\Resources\Agent;

use App\Models\Agent\Agent;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class AgentResource extends ActionResource
{
    public static function data(Agent $agent): array
    {
        return [
            'id'             => $agent->id,
            'knowledge_name' => $agent->knowledge?->name,
            'name'           => $agent->name,
            'description'    => $agent->description,
            'api'            => $agent->api,
            'model'          => $agent->model,
            'api_options'    => $agent->api_options,
            'retry_count'    => $agent->retry_count,
            'threads_count'  => $agent->threads_count,
            'created_at'     => $agent->created_at,
            'updated_at'     => $agent->updated_at,

            'threads' => fn($fields) => AgentThreadResource::collection($agent->threads()->orderByDesc('updated_at')->with('sortedMessages.storedFiles.transcodes')->limit(20)->get(), $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*'       => true,
            'threads' => ['messages' => ['files' => ['transcodes' => true]]],
        ]);
    }
}
