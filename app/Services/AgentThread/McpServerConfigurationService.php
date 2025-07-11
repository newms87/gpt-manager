<?php

namespace App\Services\AgentThread;

use App\Models\Agent\McpServer;
use App\Models\Task\TaskDefinition;

class McpServerConfigurationService
{
    public function getMcpServerToolsForTaskDefinition(TaskDefinition $taskDefinition): array
    {
        $mcpServerIds = $taskDefinition->task_runner_config['mcp_server_ids'] ?? [];
        
        if (empty($mcpServerIds)) {
            return [];
        }

        $mcpServers = McpServer::whereIn('id', $mcpServerIds)
            ->where('team_id', $taskDefinition->team_id)
            ->where('is_active', true)
            ->get();

        $tools = [];
        
        foreach ($mcpServers as $mcpServer) {
            $tools[] = [
                'type' => 'mcp',
                'server_url' => $mcpServer->server_url,
                'server_label' => $mcpServer->label,
                'allowed_tools' => $mcpServer->allowed_tools,
                'require_approval' => $mcpServer->require_approval,
                'headers' => $mcpServer->headers
            ];
        }

        return $tools;
    }
}