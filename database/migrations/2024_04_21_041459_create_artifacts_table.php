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
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('group_number');
            $table->string('name');
            $table->string('model');
            $table->json('data');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('artifactables', function (Blueprint $table) {
            $table->unsignedInteger('artifact_id');
            $table->unsignedInteger('artifactable_id');
            $table->string('artifactable_type');
            $table->string('category');
            $table->timestamps();

            $table->primary(['artifact_id', 'artifactable_id', 'artifactable_type'], 'artifactables_primary');
            $table->index(['artifactable_id', 'artifactable_type'], 'artifactables_artifactable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
