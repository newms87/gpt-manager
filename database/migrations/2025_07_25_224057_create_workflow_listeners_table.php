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
        Schema::create('workflow_listeners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->morphs('listener'); // Creates listener_type and listener_id columns
            $table->string('workflow_type'); // e.g., 'extract_data', 'write_demand'
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['listener_type', 'listener_id'], 'workflow_listeners_morph_index');
            $table->index(['workflow_type', 'status'], 'workflow_listeners_type_status_index');
            $table->unique(['workflow_run_id', 'listener_type', 'listener_id', 'workflow_type'], 'workflow_listener_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_listeners');
    }
};
