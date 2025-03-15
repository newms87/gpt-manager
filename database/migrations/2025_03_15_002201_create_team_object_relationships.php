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
        Schema::create('team_object_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_object_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_team_object_id')->constrained('team_objects')->onDelete('cascade');
            $table->string('relationship_name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_object_id', 'related_team_object_id', 'relationship_name'], 'team_object_relationship_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_object_relationships');
    }
};
