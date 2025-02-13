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
        Schema::table('task_runs', function (Blueprint $table) {
            $table->foreignId('task_workflow_run_id')->nullable()->after('task_definition_id')->constrained()->onDelete('set null');
            $table->foreignId('task_workflow_node_id')->nullable()->after('task_workflow_run_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropForeign(['task_workflow_run_id']);
            $table->dropColumn('task_workflow_run_id');

            $table->dropForeign(['task_workflow_node_id']);
            $table->dropColumn('task_workflow_node_id');
        });
    }
};
