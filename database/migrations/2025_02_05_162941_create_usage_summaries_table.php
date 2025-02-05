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
        Schema::create('usage_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('object_type');
            $table->uuid('object_id');
            $table->unsignedInteger('count')->default(1);
            $table->unsignedInteger('run_time_ms')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('input_cost', 12, 4)->default(0);
            $table->decimal('output_cost', 12, 4)->default(0);
            $table->decimal('total_cost', 12, 4)->storedAs('input_cost + output_cost');
            $table->timestamps();

            $table->index(['object_id', 'object_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_summaries');
    }
};
