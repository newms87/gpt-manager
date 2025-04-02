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
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->foreignId('schema_definition_id')->nullable()->after('task_runner_config')->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->after('schema_definition_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropForeign(['schema_definition_id']);
            $table->dropForeign(['agent_id']);
            $table->dropColumn(['schema_definition_id', 'agent_id']);
        });
    }
};
