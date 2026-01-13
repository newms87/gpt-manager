<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('demand_templates', 'template_definitions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('template_definitions', 'demand_templates');
    }
};
