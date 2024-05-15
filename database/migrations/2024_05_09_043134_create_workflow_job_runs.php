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
        Schema::create('workflow_job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->foreignId('workflow_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_job_runs');
    }
};
