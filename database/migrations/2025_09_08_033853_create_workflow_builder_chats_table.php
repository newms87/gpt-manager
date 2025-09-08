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
        Schema::create('workflow_builder_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_input_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_definition_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('agent_thread_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('requirements_gathering');
            $table->json('meta')->nullable();
            $table->foreignId('current_workflow_run_id')->nullable()->constrained('workflow_runs')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['team_id', 'status']);
            $table->index(['workflow_input_id', 'status']);
            $table->index('workflow_definition_id');
            $table->index('agent_thread_id');
            $table->index('current_workflow_run_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_builder_chats');
    }
};