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
            $table->dropColumn('grouping_mode');
            $table->dropColumn('split_by_file');
            $table->dropColumn('input_group_chunk_size');
            $table->json('task_runner_config')->nullable()->after('task_runner_class');
            $table->string('artifact_split_mode')->default('')->after('task_runner_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->string('grouping_mode')->nullable();
            $table->boolean('split_by_file')->default(false);
            $table->unsignedInteger('input_group_chunk_size')->default(0);
            $table->dropColumn('task_runner_config');
            $table->dropColumn('artifact_split_mode');
        });
    }
};
