<?php

namespace App\Console\Commands;

use App\Models\Agent\Agent;
use App\Models\Agent\McpServer;
use Illuminate\Console\Command;

class ListTestResources extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'prompt:resources';

    /**
     * The console command description.
     */
    protected $description = 'List available agents and MCP servers for testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->showAgents();
        $this->newLine();
        $this->showMcpServers();

        return 0;
    }

    private function showAgents(): void
    {
        $agents = Agent::all();

        if ($agents->isEmpty()) {
            $this->warn('No agents found.');

            return;
        }

        $this->info('Available Agents:');
        $this->table(['ID', 'Name', 'Model', 'Team'], $agents->map(function ($agent) {
            return [
                $agent->id,
                $agent->name,
                $agent->model,
                $agent->team->name ?? 'N/A',
            ];
        })->toArray());
    }

    private function showMcpServers(): void
    {
        $mcpServers = McpServer::all();

        if ($mcpServers->isEmpty()) {
            $this->warn('No MCP servers found.');

            return;
        }

        $this->info('Available MCP Servers:');
        $this->table(['ID', 'Name', 'Server URL', 'Team'], $mcpServers->map(function ($server) {
            return [
                $server->id,
                $server->name,
                $server->server_url,
                $server->team->name ?? 'N/A',
            ];
        })->toArray());
    }
}
