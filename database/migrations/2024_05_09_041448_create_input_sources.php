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
        Schema::create('input_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('description')->default('');
            $table->text('content')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('workflow_runs_count')->default(0);
            $table->boolean('is_transcoded')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input_sources');
    }
};
