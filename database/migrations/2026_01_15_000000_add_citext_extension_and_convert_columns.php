<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert string/text columns to CITEXT for case-insensitive comparisons.
     *
     * Note: This migration may take significant time on tables with large amounts of data.
     * The CITEXT extension provides case-insensitive text storage natively in PostgreSQL.
     */
    public function up(): void
    {
        // Enable citext extension first
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext;');

        // Define all tables and columns to convert
        $conversions = [
            'team_objects'                  => ['type', 'name', 'description'],
            'team_object_attributes'        => ['name', 'text_value', 'reason', 'confidence'],
            'team_object_relationships'     => ['relationship_name'],
            'team_object_attribute_sources' => ['source_type', 'source_id', 'explanation'],
            'teams'                         => ['name', 'namespace'],
            'users'                         => ['name', 'email'],
            'agents'                        => ['name', 'api', 'model', 'description'],
            'schema_definitions'            => ['name', 'type', 'description', 'schema_format'],
            'schema_fragments'              => ['name'],
            'schema_associations'           => ['object_type', 'category'],
            'task_definitions'              => ['name', 'task_runner_name', 'description', 'response_format', 'input_artifact_mode', 'output_artifact_mode'],
            'task_runs'                     => ['name', 'status'],
            'task_processes'                => ['name', 'status', 'activity'],
            'workflow_definitions'          => ['name', 'description'],
            'workflow_runs'                 => ['name', 'status'],
            'workflow_nodes'                => ['name'],
            'workflow_connections'          => ['name', 'source_output_port', 'target_input_port'],
            'workflow_inputs'               => ['name', 'description', 'team_object_type', 'content'],
            'artifacts'                     => ['name', 'model', 'text_content'],
            'artifactables'                 => ['category'],
            'tags'                          => ['name', 'type'],
            'object_tags'                   => ['name', 'category'],
            'prompt_directives'             => ['name', 'directive_text'],
            'knowledge'                     => ['name', 'description'],
            'content_sources'               => ['name', 'type'],
            'template_definitions'          => ['name', 'category', 'description'],
            'template_variables'            => ['name', 'description', 'mapping_type', 'multi_value_strategy', 'multi_value_separator', 'ai_instructions'],
            'permissions'                   => ['name', 'display_name', 'description'],
            'roles'                         => ['name', 'display_name', 'description'],
            'stored_files'                  => ['mime', 'transcode_name'],
            'stored_file_storables'         => ['category'],
            'mcp_servers'                   => ['name', 'description'],
            'agent_threads'                 => ['name', 'summary'],
            'agent_thread_runs'             => ['status'],
            'auth_tokens'                   => ['name', 'type'],
            'personal_access_tokens'        => ['name', 'abilities'],
            'password_reset_tokens'         => ['email'],
            'workflow_api_invocations'      => ['name'],
            'resource_packages'             => ['name', 'resource_type'],
            'resource_package_versions'     => ['version'],
            'resource_package_imports'      => ['object_type'],
            'audit_requests'                => ['method', 'user_agent'],
            'job_dispatches'                => ['ref', 'name', 'status'],
        ];

        foreach ($conversions as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                try {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE citext;");
                } catch (\Exception $e) {
                    // Log the error but continue with other columns
                    \Log::warning("Failed to convert {$table}.{$column} to citext: " . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        // Define original types for each column
        // Most are varchar(255), some are text for longer content
        $textColumns = [
            'team_objects.description',
            'team_object_attributes.text_value',
            'team_object_attributes.reason',
            'team_object_attribute_sources.explanation',
            'agents.description',
            'schema_definitions.description',
            'task_definitions.description',
            'workflow_definitions.description',
            'workflow_inputs.description',
            'workflow_inputs.content',
            'artifacts.text_content',
            'prompt_directives.directive_text',
            'knowledge.description',
            'template_definitions.description',
            'template_variables.description',
            'template_variables.ai_instructions',
            'permissions.description',
            'roles.description',
            'mcp_servers.description',
            'agent_threads.summary',
        ];

        $conversions = [
            'team_objects'                  => ['type', 'name', 'description'],
            'team_object_attributes'        => ['name', 'text_value', 'reason', 'confidence'],
            'team_object_relationships'     => ['relationship_name'],
            'team_object_attribute_sources' => ['source_type', 'source_id', 'explanation'],
            'teams'                         => ['name', 'namespace'],
            'users'                         => ['name', 'email'],
            'agents'                        => ['name', 'api', 'model', 'description'],
            'schema_definitions'            => ['name', 'type', 'description', 'schema_format'],
            'schema_fragments'              => ['name'],
            'schema_associations'           => ['object_type', 'category'],
            'task_definitions'              => ['name', 'task_runner_name', 'description', 'response_format', 'input_artifact_mode', 'output_artifact_mode'],
            'task_runs'                     => ['name', 'status'],
            'task_processes'                => ['name', 'status', 'activity'],
            'workflow_definitions'          => ['name', 'description'],
            'workflow_runs'                 => ['name', 'status'],
            'workflow_nodes'                => ['name'],
            'workflow_connections'          => ['name', 'source_output_port', 'target_input_port'],
            'workflow_inputs'               => ['name', 'description', 'team_object_type', 'content'],
            'artifacts'                     => ['name', 'model', 'text_content'],
            'artifactables'                 => ['category'],
            'tags'                          => ['name', 'type'],
            'object_tags'                   => ['name', 'category'],
            'prompt_directives'             => ['name', 'directive_text'],
            'knowledge'                     => ['name', 'description'],
            'content_sources'               => ['name', 'type'],
            'template_definitions'          => ['name', 'category', 'description'],
            'template_variables'            => ['name', 'description', 'mapping_type', 'multi_value_strategy', 'multi_value_separator', 'ai_instructions'],
            'permissions'                   => ['name', 'display_name', 'description'],
            'roles'                         => ['name', 'display_name', 'description'],
            'stored_files'                  => ['mime', 'transcode_name'],
            'stored_file_storables'         => ['category'],
            'mcp_servers'                   => ['name', 'description'],
            'agent_threads'                 => ['name', 'summary'],
            'agent_thread_runs'             => ['status'],
            'auth_tokens'                   => ['name', 'type'],
            'personal_access_tokens'        => ['name', 'abilities'],
            'password_reset_tokens'         => ['email'],
            'workflow_api_invocations'      => ['name'],
            'resource_packages'             => ['name', 'resource_type'],
            'resource_package_versions'     => ['version'],
            'resource_package_imports'      => ['object_type'],
            'audit_requests'                => ['method', 'user_agent'],
            'job_dispatches'                => ['ref', 'name', 'status'],
        ];

        foreach ($conversions as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                $fullColumnName = "{$table}.{$column}";
                $type           = in_array($fullColumnName, $textColumns) ? 'text' : 'varchar(255)';

                try {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE {$type};");
                } catch (\Exception $e) {
                    \Log::warning("Failed to revert {$table}.{$column} to {$type}: " . $e->getMessage());
                }
            }
        }

        // Optionally drop extension (commented out - may affect other things)
        // DB::statement('DROP EXTENSION IF EXISTS citext;');
    }
};
