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
        Schema::create('task_definition_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained();
            $table->foreignId('agent_id')->constrained();
            $table->boolean('include_text')->default(false);
            $table->boolean('include_files')->default(false);
            $table->boolean('include_data')->default(false);
            $table->foreignId('input_schema_id')->nullable()->constrained('prompt_schemas');
            $table->foreignId('input_schema_fragment_id')->nullable()->constrained('prompt_schema_fragments');
            $table->foreignId('output_schema_id')->nullable()->constrained('prompt_schemas');
            $table->foreignId('output_schema_fragment_id')->nullable()->constrained('prompt_schema_fragments');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_definition_agents');
    }
};
