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
        Schema::create('task_workflow_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_node_id')->constrained('task_workflow_nodes')->onDelete('cascade');
            $table->foreignId('target_node_id')->constrained('task_workflow_nodes')->onDelete('cascade');
            $table->string('source_output_port');
            $table->string('target_input_port');
            $table->string('name')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_workflow_connections');
    }
};
