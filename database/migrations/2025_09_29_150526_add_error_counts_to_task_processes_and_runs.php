<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_processes', function (Blueprint $table) {
            $table->unsignedInteger('error_count')->default(0)->after('restart_count');
        });

        Schema::table('task_runs', function (Blueprint $table) {
            $table->unsignedInteger('task_process_error_count')->default(0)->after('process_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropColumn('error_count');
        });

        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropColumn('task_process_error_count');
        });
    }
};
