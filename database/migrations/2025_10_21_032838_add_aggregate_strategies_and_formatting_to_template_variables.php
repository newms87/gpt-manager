<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the check constraint created by the enum in PostgreSQL
        DB::statement('ALTER TABLE template_variables DROP CONSTRAINT IF EXISTS template_variables_multi_value_strategy_check');

        Schema::table('template_variables', function (Blueprint $table) {
            // Change multi_value_strategy to string to support aggregate operations
            $table->string('multi_value_strategy')
                ->default('join')
                ->nullable()
                ->change();

            // Add value formatting fields
            $table->string('value_format_type')
                ->default('text')
                ->nullable()
                ->after('multi_value_separator');
            $table->unsignedTinyInteger('decimal_places')
                ->default(2)
                ->nullable()
                ->after('value_format_type');
            $table->string('currency_code', 3)
                ->default('USD')
                ->nullable()
                ->after('decimal_places');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // Drop formatting fields
            $table->dropColumn(['value_format_type', 'decimal_places', 'currency_code']);

            // Revert multi_value_strategy back to original (no change needed - still string)
        });
    }
};
