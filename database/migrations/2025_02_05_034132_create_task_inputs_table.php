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
        Schema::create('task_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_input_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('task_run_count')->default(0);
            $table->timestamps();

            $table->unique(['task_definition_id', 'workflow_input_id']);
        });

        Schema::table('task_runs', function (Blueprint $table) {
            $table->foreignId('task_input_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropForeign(['task_input_id']);
            $table->dropColumn('task_input_id');
        });

        Schema::dropIfExists('task_inputs');
    }
};
