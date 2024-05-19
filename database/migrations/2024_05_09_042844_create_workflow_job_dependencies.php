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
        Schema::create('workflow_job_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_workflow_job_id')->constrained('workflow_jobs')->cascadeOnDelete();
            $table->string('group_by')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_job_dependencies');
    }
};
