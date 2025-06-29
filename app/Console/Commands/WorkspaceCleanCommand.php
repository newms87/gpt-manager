<?php

namespace App\Console\Commands;

use App\Models\Agent\AgentThreadMessage;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use DB;
use Illuminate\Console\Command;

class WorkspaceCleanCommand extends Command
{
    protected $signature   = 'workspace:clean {--team-objects} {--runs} {--inputs} {--auditing}';
    protected $description = 'Deletes workspace data based on flags: team objects, all runs, agent threads and messages, ';

    public function handle(): void
    {
        $this->info("Starting workspace cleaning\n\n");

        if ($this->option('team-objects')) {
            $this->cleanTeamObjects();
        }

        if ($this->option('runs')) {
            $this->cleanRuns();
        }

        if ($this->option('inputs')) {
            $this->cleanInputs();
        }

        if ($this->option('auditing')) {
            $this->cleanAuditing();
        }

        $this->info("Data cleaning completed successfully");
    }

    private function cleanInputs(): void
    {
        $this->alert("Cleaning all inputs");

        $tables = [
            'task_inputs',
            'stored_file_storables',
            'stored_files',
            'workflow_inputs',
        ];

        $this->truncateTables($tables);

        $this->info("All inputs have been cleaned\n\n");
    }

    private function cleanRuns(): void
    {
        $this->alert("Cleaning all runs and artifacts");

        $tables = [
            'agent_thread_messageables',
            'agent_thread_messages',
            'agent_thread_runs',
            'agent_threads',
            'artifactables',
            'artifacts',
            'task_process_listeners',
            'task_processes',
            'task_runs',
            'workflow_runs',
        ];

        $this->truncateTables($tables);

        $counts = [
            'agents'               => [
                'threads_count',
            ],
            'task_definitions'     => [
                'task_run_count',
            ],
            'workflow_definitions' => [
                'workflow_runs_count',
            ],
        ];

        $this->comment("Cleaning task process schema associations");
        SchemaAssociation::where('object_type', TaskProcess::class)->delete();
        DB::statement("DELETE FROM stored_file_storables WHERE storable_type IN ('" . AgentThreadMessage::class . "','" . Artifact::class . "')");
        $this->resetCounts($counts);

        $this->info("All runs have been cleaned\n\n");
    }

    private function cleanTeamObjects(): void
    {
        $this->alert("Cleaning team objects");

        $tables = [
            'team_object_attribute_sources',
            'team_object_relationships',
            'team_object_attributes',
            'team_objects',
        ];

        $this->truncateTables($tables);

        $this->info("Team objects have been cleaned\n\n");
    }

    private function cleanAuditing(): void
    {
        $this->alert("Cleaning auditing");

        $tables = [
            'audits',
            'api_logs',
            'audit_request',
            'error_log_entry',
            'error_logs',
            'job_batches',
            'job_dispatch',
            'job_dispatchables',
        ];

        $this->truncateTables($tables);

        $this->info("Auditing has been cleaned\n\n");
    }

    private function truncateTables($tables): void
    {
        // Disable FK checks in postgres
        DB::statement('SET CONSTRAINTS ALL DEFERRED;');
        foreach($tables as $table) {
            $this->warn("Truncating table: $table");
            DB::table($table)->truncate();
        }
        // Enable FK checks in postgres
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE;');

    }

    private function resetCounts($counts): void
    {
        foreach($counts as $table => $columns) {
            foreach($columns as $column) {
                $this->warn("Resetting counts for $table.$column");
                DB::table($table)->update([$column => 0]);
            }
        }
    }
}
