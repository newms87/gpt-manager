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
        Schema::create('workflow_input_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_input_id')->constrained()->onDelete('cascade');
            $table->string('associable_type');
            $table->unsignedBigInteger('associable_id')->nullable();
            $table->string('category', 100)->default('write_demand_instructions');
            $table->timestamps();

            $table->index(['associable_type', 'associable_id']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_input_associations');
    }
};