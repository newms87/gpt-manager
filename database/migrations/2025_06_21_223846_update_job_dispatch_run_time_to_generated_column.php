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
        if (Schema::hasTable('job_dispatch')) {
            Schema::table('job_dispatch', function (Blueprint $table) {
                // Drop existing run_time column if it exists and is not generated
                if (Schema::hasColumn('job_dispatch', 'run_time')) {
                    $table->dropColumn('run_time');
                }
            });

            Schema::table('job_dispatch', function (Blueprint $table) {
                // Add run_time as a generated column
                $table->integer('run_time')->nullable()
                    ->storedAs('EXTRACT(EPOCH FROM (completed_at - ran_at))::integer')
                    ->after('timeout_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('job_dispatch')) {
            Schema::table('job_dispatch', function (Blueprint $table) {
                // Drop the generated column
                if (Schema::hasColumn('job_dispatch', 'run_time')) {
                    $table->dropColumn('run_time');
                }
            });

            Schema::table('job_dispatch', function (Blueprint $table) {
                // Add back as regular nullable integer column
                $table->integer('run_time')->nullable()->after('timeout_at');
            });
        }
    }
};
