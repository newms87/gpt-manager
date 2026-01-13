<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['demand_template_id']);

            // Rename the column
            $table->renameColumn('demand_template_id', 'template_definition_id');
        });

        Schema::table('template_variables', function (Blueprint $table) {
            // Add the new foreign key constraint
            $table->foreign('template_definition_id')
                ->references('id')
                ->on('template_definitions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // Drop the new foreign key constraint
            $table->dropForeign(['template_definition_id']);

            // Rename the column back
            $table->renameColumn('template_definition_id', 'demand_template_id');
        });

        Schema::table('template_variables', function (Blueprint $table) {
            // Add back the old foreign key constraint
            $table->foreign('demand_template_id')
                ->references('id')
                ->on('demand_templates')
                ->onDelete('cascade');
        });
    }
};
