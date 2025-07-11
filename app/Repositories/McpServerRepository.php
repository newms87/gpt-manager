<?php

namespace App\Repositories;

use App\Models\Agent\McpServer;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class McpServerRepository extends ActionRepository
{
    public static string $model = McpServer::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createMcpServer($data),
            'update' => $this->updateMcpServer($model, $data),
            'copy' => $this->copyMcpServer($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createMcpServer(array $data): McpServer
    {
        $mcpServer = McpServer::make()->forceFill([
            'team_id' => team()->id,
        ]);

        $mcpServer->fill($data);
        $mcpServer->name = ModelHelper::getNextModelName($mcpServer);


        $mcpServer->validate()->save();

        return $mcpServer;
    }

    public function updateMcpServer(McpServer $mcpServer, array $data): McpServer
    {
        $mcpServer->fill($data)->validate()->save($data);

        return $mcpServer;
    }

    public function copyMcpServer(McpServer $mcpServer): McpServer
    {
        $newMcpServer       = $mcpServer->replicate();
        $newMcpServer->name = ModelHelper::getNextModelName($mcpServer);
        $newMcpServer->save();

        return $newMcpServer;
    }
}
