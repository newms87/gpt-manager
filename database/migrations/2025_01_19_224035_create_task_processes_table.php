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
        Schema::create('task_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_run_id')->constrained()->onDelete('cascade');
            $table->foreignId('thread_id')->nullable();
            $table->foreignId('last_job_dispatch_id')->nullable()->constrained('job_dispatch')->onDelete('set null');
            $table->string('status')->default('Pending');
            $table->datetime('started_at')->nullable();
            $table->datetime('stopped_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->datetime('timeout_at')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_processes');
    }
};
