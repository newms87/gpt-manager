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
        Schema::create('task_workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_workflow_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('Pending');
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('stopped_at', 3)->nullable();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamp('failed_at', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_workflow_runs');
    }
};
