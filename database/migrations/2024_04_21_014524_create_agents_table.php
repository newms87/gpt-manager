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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->foreignId('knowledge_id')->nullable()->constrained();
            $table->string('name');
            $table->string('description', 1024)->default('');
            $table->string('model')->default('');
            $table->decimal('temperature', 5, 2)->default(0);
            $table->json('functions')->nullable();
            $table->text('prompt')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
