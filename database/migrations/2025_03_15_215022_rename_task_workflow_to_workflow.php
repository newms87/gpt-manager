<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['response_schema_id']);
            $table->dropForeign(['response_schema_fragment_id']);
            $table->dropColumn('response_format');
            $table->dropColumn('response_schema_id');
            $table->dropColumn('response_schema_fragment_id');
        });

        Schema::rename('task_workflows', 'workflow_definitions');
        Schema::rename('task_workflow_connections', 'workflow_connections');
        Schema::rename('task_workflow_nodes', 'workflow_nodes');
        Schema::rename('task_workflow_runs', 'workflow_runs');

        Schema::table('task_runs', function (Blueprint $table) {
            $table->renameColumn('task_workflow_run_id', 'workflow_run_id');
            $table->renameColumn('task_workflow_node_id', 'workflow_node_id');
        });

        Schema::table('workflow_connections', function (Blueprint $table) {
            $table->renameColumn('task_workflow_id', 'workflow_definition_id');
        });

        Schema::table('workflow_nodes', function (Blueprint $table) {
            $table->renameColumn('task_workflow_id', 'workflow_definition_id');
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->renameColumn('task_workflow_id', 'workflow_definition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('response_format')->nullable();
            $table->foreignId('response_schema_id')->nullable()->constrained('schema_definitions');
            $table->foreignId('response_schema_fragment_id')->nullable()->constrained('schema_fragments');
        });

        Schema::table('task_runs', function (Blueprint $table) {
            $table->renameColumn('workflow_run_id', 'task_workflow_run_id');
            $table->renameColumn('workflow_node_id', 'task_workflow_node_id');
        });

        Schema::table('workflow_connections', function (Blueprint $table) {
            $table->renameColumn('workflow_definition_id', 'task_workflow_id');
        });

        Schema::table('workflow_nodes', function (Blueprint $table) {
            $table->renameColumn('workflow_definition_id', 'task_workflow_id');
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->renameColumn('workflow_definition_id', 'task_workflow_id');
        });

        Schema::rename('workflow_definitions', 'task_workflows');
        Schema::rename('workflow_connections', 'task_workflow_connections');
        Schema::rename('workflow_nodes', 'task_workflow_nodes');
        Schema::rename('workflow_runs', 'task_workflow_runs');
    }
};
