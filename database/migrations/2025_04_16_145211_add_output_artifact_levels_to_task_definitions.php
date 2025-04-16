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
            $table->json('output_artifact_levels')->nullable()->after('output_artifact_mode');
            $table->renameColumn('artifact_split_mode', 'input_artifact_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('output_artifact_levels');
            $table->renameColumn('input_artifact_mode', 'artifact_split_mode');
        });
    }
};
