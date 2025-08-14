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
        Schema::create('ui_demand_workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ui_demand_id')->constrained('ui_demands')->onDelete('cascade');
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->onDelete('cascade');
            $table->string('workflow_type'); // 'extract_data' or 'write_demand'
            $table->timestamps();
            
            $table->unique(['ui_demand_id', 'workflow_run_id']);
            $table->index(['ui_demand_id', 'workflow_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ui_demand_workflow_runs');
    }
};
