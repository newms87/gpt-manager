<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifact_category_definitions', function (Blueprint $table) {
            $table->dropUnique(['schema_definition_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('artifact_category_definitions', function (Blueprint $table) {
            $table->unique(['schema_definition_id', 'name']);
        });
    }
};
