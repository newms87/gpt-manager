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
        Schema::create('thread_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained();
            $table->foreignId('last_message_id')->nullable()->constrained('messages');
            $table->string('status');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->json('tools')->nullable();
            $table->string('tool_choice')->default('auto');
            $table->string('response_format')->default('text');
            $table->string('seed')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->datetime('refreshed_at')->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_runs');
    }
};
