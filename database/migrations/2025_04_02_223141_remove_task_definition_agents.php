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
        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_definition_agent_id');
        });

        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('task_agent_count');
        });

        Schema::dropIfExists('task_definition_agents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('task_definition_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('resource_package_import_id');
            $table->foreignId('task_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->boolean('include_text')->default(0);
            $table->boolean('include_json')->default(0);
            $table->boolean('include_files')->default(0);
            $table->timestamps();
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->foreignId('task_definition_agent_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('task_definitions', function (Blueprint $table) {
            $table->integer('task_agent_count')->default(0);
        });
    }
};
