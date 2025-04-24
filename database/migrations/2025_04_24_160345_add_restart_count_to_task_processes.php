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
            $table->unsignedInteger('restart_count')->default(0)->after('timeout_at');
        });

        Schema::table('task_definitions', function (Blueprint $table) {
            $table->unsignedInteger('max_process_retries')->default(3)->after('timeout_after_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropColumn('restart_count');
        });

        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('max_process_retries');
        });
    }
};
