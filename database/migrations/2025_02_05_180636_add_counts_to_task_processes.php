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
            $table->unsignedInteger('job_dispatch_count')->default(0)->after('timeout_at');
            $table->unsignedInteger('input_artifact_count')->default(0)->after('job_dispatch_count');
            $table->unsignedInteger('output_artifact_count')->default(0)->after('input_artifact_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropColumn('job_dispatch_count');
            $table->dropColumn('input_artifact_count');
            $table->dropColumn('output_artifact_count');
        });
    }
};
