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
        Schema::create('object_tags', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['category', 'name'], 'object_tags_category_name_unique');
        });

        Schema::create('object_tag_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['object_tag_id', 'taggable_id', 'taggable_type'], 'object_tag_taggables_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_tag_taggables');
        Schema::dropIfExists('object_tags');
    }
};
