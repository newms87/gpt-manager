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
        if (Schema::hasColumn('workflow_inputs', 'workflow_runs_count')) {
            Schema::table('workflow_inputs', function (Blueprint $table) {
                $table->dropColumn('workflow_runs_count');
            });
        }

        if (Schema::hasColumn('agents', 'assignments_count')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropColumn('assignments_count');
            });
        }

        if (Schema::hasColumn('schema_definitions', 'workflow_jobs_count')) {
            Schema::table('schema_definitions', function (Blueprint $table) {
                $table->dropColumn('workflow_jobs_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->unsignedInteger('workflow_runs_count')->default(0);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedInteger('assignments_count')->default(0);
        });

        Schema::table('schema_definitions', function (Blueprint $table) {
            $table->unsignedInteger('workflow_jobs_count')->default(0);
        });
    }
};
