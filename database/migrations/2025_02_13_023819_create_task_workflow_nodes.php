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
        Schema::create('task_workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_definition_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('settings')->nullable();
            $table->json('params')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_workflow_nodes');
    }
};
