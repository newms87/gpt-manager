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
        \App\Models\Workflow\WorkflowJobDependency::query()->update(['group_by' => '[]']);
        Schema::table('workflow_job_dependencies', function (Blueprint $table) {
            $table->json('group_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_job_dependencies', function (Blueprint $table) {
            $table->string('group_by')->nullable(false)->default('')->change();
        });
    }
};
