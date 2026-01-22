<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_category_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_definition_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('label');
            $table->text('prompt');
            $table->jsonb('fragment_selector')->nullable();
            $table->boolean('editable')->default(true);
            $table->boolean('deletable')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['schema_definition_id', 'name']);
        });

        // Convert name to CITEXT for case-insensitive comparisons
        DB::statement('ALTER TABLE artifact_category_definitions ALTER COLUMN name TYPE citext;');
        DB::statement('ALTER TABLE artifact_category_definitions ALTER COLUMN label TYPE citext;');
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_category_definitions');
    }
};
