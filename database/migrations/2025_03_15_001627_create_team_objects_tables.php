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
        Schema::create('team_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('schema_definition_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('root_object_id')->nullable()->constrained('team_objects')->onDelete('set null');
            $table->string('type');
            $table->string('name');
            $table->dateTime('date')->nullable();
            $table->text('description')->nullable();
            $table->text('url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_objects');
    }
};
