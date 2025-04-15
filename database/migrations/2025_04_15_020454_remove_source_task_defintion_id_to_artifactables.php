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
        Schema::table('artifactables', function (Blueprint $table) {
            $table->dropForeign(['source_task_definition_id']);
            $table->dropColumn('source_task_definition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artifactables', function (Blueprint $table) {
            $table->foreignId('source_task_definition_id')
                ->nullable()
                ->constrained('task_definitions')
                ->cascadeOnDelete();
        });
    }
};
