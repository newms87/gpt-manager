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
        Schema::create('task_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->string('name');
            $table->string('description');
            $table->string('task_service');
            $table->json('input_grouping')->nullable();
            $table->unsignedInteger('input_group_chunk_size')->default(1);
            $table->unsignedInteger('task_run_count')->default(0);
            $table->unsignedInteger('task_agent_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_definitions');
    }
};
