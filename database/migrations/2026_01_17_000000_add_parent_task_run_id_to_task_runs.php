<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->foreignId('parent_task_run_id')
                ->nullable()
                ->after('id')
                ->constrained('task_runs')
                ->nullOnDelete();

            $table->unsignedInteger('restart_count')
                ->default(0)
                ->after('task_process_error_count');

            $table->index('parent_task_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropForeign(['parent_task_run_id']);
            $table->dropIndex(['parent_task_run_id']);
            $table->dropColumn(['parent_task_run_id', 'restart_count']);
        });
    }
};
