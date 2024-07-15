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
        Schema::table('workflow_job_dependencies', function (Blueprint $table) {
            $table->json('include_fields')->nullable()->after('depends_on_workflow_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_job_dependencies', function (Blueprint $table) {
            $table->dropColumn('include_fields');
        });
    }
};
