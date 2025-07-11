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
        Schema::table('mcp_servers', function (Blueprint $table) {
            // Drop the global unique constraint on label
            $table->dropUnique(['label']);
            
            // Make label nullable since it can be auto-generated from name
            $table->string('label')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            // Restore the global unique constraint on label
            $table->string('label')->unique()->change();
        });
    }
};
