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
            $table->unsignedInteger('input_artifacts_count')->default(0)->after('process_count');
            $table->unsignedInteger('output_artifacts_count')->default(0)->after('input_artifacts_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropColumn('input_artifacts_count');
            $table->dropColumn('output_artifacts_count');
        });
    }
};
