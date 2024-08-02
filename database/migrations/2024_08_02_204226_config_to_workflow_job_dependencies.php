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
            $table->boolean('force_schema')->default(false)->after('depends_on_workflow_job_id');
            $table->json('order_by')->nullable()->after('group_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_job_dependencies', function (Blueprint $table) {
            $table->dropColumn('force_schema');
            $table->dropColumn('order_by');
        });
    }
};
