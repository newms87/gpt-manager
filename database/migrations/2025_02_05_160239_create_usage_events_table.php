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
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('object_type');
            $table->uuid('object_id');
            $table->string('event_type');
            $table->unsignedInteger('run_time_ms')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('input_cost', 12, 4)->default(0);
            $table->decimal('output_cost', 12, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'object_type', 'object_id']);
            $table->index(['event_type', 'object_type', 'object_id']);
            $table->index(['object_id', 'object_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
