<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'verbatim' to the mapping_type enum in PostgreSQL
        DB::statement("ALTER TABLE template_variables DROP CONSTRAINT IF EXISTS template_variables_mapping_type_check");
        DB::statement("ALTER TABLE template_variables ADD CONSTRAINT template_variables_mapping_type_check CHECK (mapping_type IN ('ai', 'artifact', 'team_object', 'verbatim'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'verbatim' from the mapping_type enum
        // First update any 'verbatim' records to 'artifact' as a fallback
        DB::statement("UPDATE template_variables SET mapping_type = 'artifact' WHERE mapping_type = 'verbatim'");

        DB::statement("ALTER TABLE template_variables DROP CONSTRAINT IF EXISTS template_variables_mapping_type_check");
        DB::statement("ALTER TABLE template_variables ADD CONSTRAINT template_variables_mapping_type_check CHECK (mapping_type IN ('ai', 'artifact', 'team_object'))");
    }
};
