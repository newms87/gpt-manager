<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_processes', function (Blueprint $table) {
            $table->foreignId('parent_task_process_id')
                ->nullable()
                ->after('task_run_id')
                ->constrained('task_processes')
                ->nullOnDelete();

            $table->index('parent_task_process_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropForeign(['parent_task_process_id']);
            $table->dropIndex(['parent_task_process_id']);
            $table->dropColumn('parent_task_process_id');
        });
    }
};
