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
        Schema::create('schema_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_definition_id')->constrained('prompt_schemas')->onDelete('cascade');
            $table->foreignId('schema_fragment_id')->constrained('prompt_schema_fragments')->onDelete('cascade');
            $table->morphs('object');
            $table->string('category')->default('');
            $table->timestamps();
        });

        Schema::table('prompt_schemas', function (Blueprint $table) {
            $table->unsignedInteger('fragments_count')->default(0)->after('agents_count');
            $table->unsignedInteger('associations_count')->default(0)->after('fragments_count');
        });

        Schema::table('prompt_schema_fragments', function (Blueprint $table) {
            $table->unsignedInteger('associations_count')->default(0)->after('fragment_selector');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_associations');

        Schema::table('prompt_schemas', function (Blueprint $table) {
            $table->dropColumn('fragments_count');
            $table->dropColumn('associations_count');
        });

        Schema::table('prompt_schema_fragments', function (Blueprint $table) {
            $table->dropColumn('associations_count');
        });
    }
};
