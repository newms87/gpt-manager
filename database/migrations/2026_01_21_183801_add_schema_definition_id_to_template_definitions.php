<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->foreignId('schema_definition_id')
                ->nullable()
                ->after('user_id')
                ->constrained('schema_definitions')
                ->nullOnDelete();

            $table->index('schema_definition_id');
        });
    }

    public function down(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->dropForeign(['schema_definition_id']);
            $table->dropIndex(['schema_definition_id']);
            $table->dropColumn('schema_definition_id');
        });
    }
};
