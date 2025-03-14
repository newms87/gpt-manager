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
        Schema::table('task_workflow_runs', function (Blueprint $table) {
            $table->boolean('has_run_all_tasks')->default(0)->after('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_workflow_runs', function (Blueprint $table) {
            $table->dropColumn('has_run_all_tasks');
        });
    }
};
