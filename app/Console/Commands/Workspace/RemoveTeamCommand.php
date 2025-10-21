<?php

namespace App\Console\Commands\Workspace;

use App\Models\Team\Team;
use DB;
use Illuminate\Console\Command;

class RemoveTeamCommand extends Command
{
    protected $signature   = 'workspace:remove-team {team : Team ID or team name}';
    protected $description = 'Thoroughly remove a team and all related data (excluding users)';

    public function handle(): void
    {
        $teamIdentifier = $this->argument('team');
        $team           = $this->resolveTeam($teamIdentifier);

        if (!$team) {
            $this->error("Team not found: $teamIdentifier");

            return;
        }

        $this->displayTeamInfo($team);

        if (!$this->confirmDeletion($team)) {
            $this->info('Team removal cancelled.');

            return;
        }

        $this->removeTeam($team);
        $this->info("Team '{$team->name}' has been completely removed.");
    }

    private function resolveTeam(string $identifier): ?Team
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return Team::where('uuid', $identifier)->orWhere('id', $identifier)->first();
        }

        return Team::where('name', $identifier)->first();
    }

    private function displayTeamInfo(Team $team): void
    {
        $this->info("\n=== Team Information ===");
        $this->info("ID: {$team->id}");
        $this->info("UUID: {$team->uuid}");
        $this->info("Name: {$team->name}");
        $this->info("Created: {$team->created_at}");

        $userCount = $team->users()->count();
        $this->info("Users: {$userCount}");

        $this->info("\n=== Direct Team Data ===");
        $this->info("Agents: {$team->agents()->count()}");
        $this->info("Task Definitions: {$team->taskDefinitions()->count()}");
        $this->info("Workflow Definitions: {$team->workflowDefinitions()->count()}");
        $this->info("Schema Definitions: {$team->schemaDefinitions()->count()}");
        $this->info("Workflow Inputs: {$team->workflowInputs()->count()}");

        $teamObjectCount = DB::table('team_objects')->where('team_id', $team->id)->count();
        $this->info("Team Objects: {$teamObjectCount}");

        $usageEventCount = DB::table('usage_events')->where('team_id', $team->id)->count();
        $this->info("Usage Events: {$usageEventCount}");

        $this->info("\n=== Indirect Team Data ===");
        $agentThreadCount = DB::table('agent_threads')->where('team_id', $team->id)->count();
        $this->info("Agent Threads: {$agentThreadCount}");

        $knowledgeCount = DB::table('knowledge')->where('team_id', $team->id)->count();
        $this->info("Knowledge Bases: {$knowledgeCount}");

        $taskRunCount = DB::table('task_runs')
            ->whereIn('task_definition_id', $team->taskDefinitions()->pluck('id'))
            ->count();
        $this->info("Task Runs: {$taskRunCount}");

        $workflowRunCount = DB::table('workflow_runs')
            ->whereIn('workflow_definition_id', $team->workflowDefinitions()->pluck('id'))
            ->count();
        $this->info("Workflow Runs: {$workflowRunCount}");

        $agentThreadMessageCount = DB::table('agent_thread_messages')
            ->whereIn('agent_thread_id', DB::table('agent_threads')->where('team_id', $team->id)->pluck('id'))
            ->count();
        $this->info("Agent Thread Messages: {$agentThreadMessageCount}");

        $agentThreadRunCount = DB::table('agent_thread_runs')
            ->whereIn('agent_thread_id', DB::table('agent_threads')->where('team_id', $team->id)->pluck('id'))
            ->count();
        $this->info("Agent Thread Runs: {$agentThreadRunCount}");
    }

    private function confirmDeletion(Team $team): bool
    {
        $this->warn("\n⚠️  WARNING: This action cannot be undone!");
        $this->warn("All data associated with this team will be permanently deleted.");
        $this->warn("Users will NOT be deleted and can be reassigned to other teams.");

        $confirmation = $this->ask("Type the team name '{$team->name}' to confirm deletion:");

        return $confirmation === $team->name;
    }

    private function removeTeam(Team $team): void
    {
        $this->info("\n🗑️  Starting team removal process...");

        DB::transaction(function () use ($team) {
            $this->info("1. Disabling foreign key constraints...");
            DB::statement('SET CONSTRAINTS ALL DEFERRED;');

            $this->info("2. Removing team-user relationships...");
            $team->users()->detach();

            $this->info("3. Force deleting ALL team-related data...");
            $this->forceDeleteAllTeamData($team);

            $this->info("4. Deleting the team record...");
            DB::table('teams')->where('id', $team->id)->delete();

            $this->info("5. Re-enabling foreign key constraints...");
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE;');

            $this->info("6. Final verification...");
            $this->verifyTeamDeletion($team->id);
        });

        $this->info("✅ Team removal completed successfully!");
    }

    private function forceDeleteAllTeamData(Team $team): void
    {
        $this->info("   Getting all team-related record IDs...");

        $agentIds              = DB::table('agents')->where('team_id', $team->id)->pluck('id');
        $agentThreadIds        = DB::table('agent_threads')->where('team_id', $team->id)->pluck('id');
        $taskDefinitionIds     = DB::table('task_definitions')->where('team_id', $team->id)->pluck('id');
        $workflowDefinitionIds = DB::table('workflow_definitions')->where('team_id', $team->id)->pluck('id');

        $taskRunIds = $taskDefinitionIds->isNotEmpty() ?
            DB::table('task_runs')->whereIn('task_definition_id', $taskDefinitionIds)->pluck('id') : collect();

        $workflowRunIds = $workflowDefinitionIds->isNotEmpty() ?
            DB::table('workflow_runs')->whereIn('workflow_definition_id', $workflowDefinitionIds)->pluck('id') : collect();

        $messageIds = $agentThreadIds->isNotEmpty() ?
            DB::table('agent_thread_messages')->whereIn('agent_thread_id', $agentThreadIds)->pluck('id') : collect();

        $taskProcessIds = $taskRunIds->isNotEmpty() ?
            DB::table('task_processes')->whereIn('task_run_id', $taskRunIds)->pluck('id') : collect();

        $this->info("   Force deleting in dependency order...");

        $deletionPlan = [
            'agent_thread_messageables'     => ['agent_thread_message_id', $messageIds],
            'agent_thread_messages'         => ['agent_thread_id', $agentThreadIds],
            'agent_thread_runs'             => ['agent_thread_id', $agentThreadIds],
            'agent_threads'                 => ['team_id', [$team->id]],
            'task_processes'                => ['task_run_id', $taskRunIds],
            'artifacts'                     => ['team_id', [$team->id]],
            'task_runs'                     => ['task_definition_id', $taskDefinitionIds],
            'workflow_api_invocations'      => ['workflow_run_id', $workflowRunIds],
            'workflow_runs'                 => ['workflow_definition_id', $workflowDefinitionIds],
            'workflow_nodes'                => ['workflow_definition_id', $workflowDefinitionIds],
            'workflow_connections'          => ['workflow_definition_id', $workflowDefinitionIds],
            'task_definition_agents'        => ['task_definition_id', $taskDefinitionIds],
            'task_definition_directives'    => ['task_definition_id', $taskDefinitionIds],
            'task_inputs'                   => ['task_definition_id', $taskDefinitionIds],
            'workflow_inputs'               => ['team_id', [$team->id]],
            'agents'                        => ['team_id', [$team->id]],
            'task_definitions'              => ['team_id', [$team->id]],
            'workflow_definitions'          => ['team_id', [$team->id]],
            'schema_definitions'            => ['team_id', [$team->id]],
            'prompt_directives'             => ['team_id', [$team->id]],
            'content_sources'               => ['team_id', [$team->id]],
            'mcp_servers'                   => ['team_id', [$team->id]],
            'assistant_actions'             => ['team_id', [$team->id]],
            'knowledge'                     => ['team_id', [$team->id]],
            'usage_events'                  => ['team_id', [$team->id]],
            'team_object_attributes'        => ['team_id', [$team->id]],
            'team_object_relationships'     => ['team_id', [$team->id]],
            'team_object_attribute_sources' => ['team_id', [$team->id]],
            'team_objects'                  => ['team_id', [$team->id]],
            'resource_package_imports'      => ['team_id', [$team->id]],
        ];

        foreach($deletionPlan as $table => $deleteInfo) {
            [$column, $ids] = $deleteInfo;

            if (is_array($ids)) {
                $ids = collect($ids);
            }

            if ($ids->isNotEmpty() && DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)->whereIn($column, $ids)->count();
                if ($count > 0) {
                    $this->warn("   - Deleting {$count} records from {$table}");
                    DB::table($table)->whereIn($column, $ids)->delete();
                }
            }
        }
    }

    private function verifyTeamDeletion(int $teamId): void
    {
        $this->info("   Verifying complete team removal...");

        $verificationTables = [
            'teams',
            'agents',
            'agent_threads',
            'task_definitions',
            'workflow_definitions',
            'schema_definitions',
            'prompt_directives',
            'content_sources',
            'mcp_servers',
            'assistant_actions',
            'knowledge',
            'usage_events',
            'team_objects',
            'artifacts',
        ];

        $totalRemaining = 0;
        foreach($verificationTables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)->where('team_id', $teamId)->count();
                if ($count > 0) {
                    $this->error("   ❌ {$count} records still remain in {$table}");
                    $totalRemaining += $count;
                }
            }
        }

        if ($totalRemaining === 0) {
            $this->info("   ✅ All team data successfully removed!");
        } else {
            $this->error("   ❌ {$totalRemaining} total records still remain - something went wrong");
        }
    }
}
