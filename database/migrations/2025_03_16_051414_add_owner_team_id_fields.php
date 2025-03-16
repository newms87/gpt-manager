<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = [
        'agent_prompt_directives',
        'agents',
        'prompt_directives',
        'schema_associations',
        'schema_definitions',
        'schema_fragments',
        'task_definition_agents',
        'task_definitions',
        'workflow_connections',
        'workflow_definitions',
        'workflow_nodes',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('owner_team_id')->nullable()->after('id');
                $table->unsignedBigInteger('owner_object_id')->nullable()->after('owner_team_id');
                $table->string('version_hash')->nullable()->after('owner_object_id');
                $table->string('version_date')->nullable()->after('version_hash');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('owner_team_id');
                $table->dropColumn('owner_object_id');
                $table->dropColumn('version_hash');
                $table->dropColumn('version_date');
            });
        }
    }
};
