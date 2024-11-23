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
        Schema::create('prompt_schema_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_schema_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->json('schema');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_schema_history');
    }
};
