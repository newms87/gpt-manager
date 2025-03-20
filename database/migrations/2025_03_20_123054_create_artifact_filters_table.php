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
        Schema::create('task_artifact_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_task_definition_id')->constrained('task_definitions')->cascadeOnDelete();
            $table->foreignId('target_task_definition_id')->constrained('task_definitions')->cascadeOnDelete();
            $table->boolean('include_text')->default(true);
            $table->boolean('include_files')->default(true);
            $table->boolean('include_json')->default(true);
            $table->json('fragment_selector')->nullable();
            $table->timestamps();

            $table->unique(['source_task_definition_id', 'target_task_definition_id'], 'task_artifact_filters_unique');
        });

        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignId('task_definition_id')->nullable()->after('schema_definition_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_artifact_filters');
    }
};
