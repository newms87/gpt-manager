<?php

namespace App\Resources\Agent;

use App\Models\Agent\McpServer;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class McpServerResource extends ActionResource
{
    public static function data(McpServer $mcpServer): array
    {
        return [
            'id'            => $mcpServer->id,
            'name'          => $mcpServer->name,
            'description'   => $mcpServer->description,
            'server_url'    => $mcpServer->server_url,
            'headers'       => $mcpServer->headers,
            'allowed_tools' => $mcpServer->allowed_tools,
            'created_at'    => $mcpServer->created_at,
            'updated_at'    => $mcpServer->updated_at,
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*' => true,
        ]);
    }
}
