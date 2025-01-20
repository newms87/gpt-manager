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
        Schema::create('task_definition_agent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained();
            $table->foreignId('agent_id')->constrained();
            $table->foreignId('input_schema_id')->nullable()->constrained('prompt_schemas');
            $table->json('input_sub_selection')->nullable();
            $table->foreignId('output_schema_id')->nullable()->constrained('prompt_schemas');
            $table->json('output_sub_selection')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_definition_agent');
    }
};
