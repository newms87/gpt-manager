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
        Schema::create('team_object_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_object_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('text_value')->nullable();
            $table->json('json_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('confidence')->nullable();
            $table->foreignId('agent_thread_run_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_object_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_object_attributes');
    }
};
