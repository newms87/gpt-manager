<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assistant_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_thread_id')->constrained()->cascadeOnDelete();
            $table->string('context'); // schema-editor, workflow-editor, general-chat, etc.
            $table->string('action_type'); // create, update, delete, validate, etc.
            $table->string('target_type'); // schema, workflow, agent, etc.
            $table->string('target_id')->nullable(); // ID of the target object
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed, cancelled
            $table->string('title'); // Human-readable action description
            $table->text('description')->nullable(); // Detailed description
            $table->json('payload')->nullable(); // Action-specific data
            $table->json('preview_data')->nullable(); // Data for showing preview
            $table->json('result_data')->nullable(); // Result of the action
            $table->text('error_message')->nullable(); // Error details if failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['team_id', 'context']);
            $table->index(['agent_thread_id', 'status']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_actions');
    }
};
