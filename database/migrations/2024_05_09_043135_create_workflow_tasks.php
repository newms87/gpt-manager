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
        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('workflow_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_job_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained();
            $table->foreignId('artifact_id')->nullable()->constrained();
            $table->string('status');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->foreignId('job_dispatch_id')->nullable()->constrained('job_dispatch');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_tasks');
    }
};
