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
            $table->json('input_artifact_levels')->nullable()->after('artifact_split_mode');
            $table->string('output_artifact_mode')->default('')->after('input_artifact_levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('input_artifact_levels');
            $table->dropColumn('output_artifact_mode');
        });
    }
};
