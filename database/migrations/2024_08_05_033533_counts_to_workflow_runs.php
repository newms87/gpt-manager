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
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->unsignedInteger('artifacts_count')->default(0)->after('status');
            $table->unsignedInteger('job_runs_count')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn('artifacts_count');
            $table->dropColumn('job_runs_count');
        });
    }
};
