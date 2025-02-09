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
        Schema::rename('prompt_schemas', 'schema_definitions');
        Schema::rename('prompt_schema_fragments', 'schema_fragments');
        Schema::rename('prompt_schema_history', 'schema_history');

        Schema::table('schema_fragments', function (Blueprint $table) {
            $table->renameColumn('prompt_schema_id', 'schema_definition_id');
        });

        Schema::table('schema_history', function (Blueprint $table) {
            $table->renameColumn('prompt_schema_id', 'schema_definition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('schema_definitions', 'prompt_schemas');
        Schema::rename('schema_fragments', 'prompt_schema_fragments');
        Schema::rename('schema_history', 'prompt_schema_history');

        Schema::table('prompt_schema_fragments', function (Blueprint $table) {
            $table->renameColumn('schema_definition_id', 'schema_definition_id');
        });

        Schema::table('prompt_schema_history', function (Blueprint $table) {
            $table->renameColumn('schema_definition_id', 'schema_definition_id');
        });
    }
};
