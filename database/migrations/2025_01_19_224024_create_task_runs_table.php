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
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained();
            $table->string('status')->default('Pending');
            $table->datetime('started_at')->nullable();
            $table->datetime('stopped_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('process_count');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_runs');
    }
};
