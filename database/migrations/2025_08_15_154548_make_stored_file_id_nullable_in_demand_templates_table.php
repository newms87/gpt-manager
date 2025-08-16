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
        Schema::table('demand_templates', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['stored_file_id']);
            
            // Make the column nullable
            $table->char('stored_file_id', 36)->nullable()->change();
            
            // Re-add the foreign key constraint
            $table->foreign('stored_file_id')->references('id')->on('stored_files')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demand_templates', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['stored_file_id']);
            
            // Make the column not nullable
            $table->char('stored_file_id', 36)->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('stored_file_id')->references('id')->on('stored_files')->cascadeOnDelete();
        });
    }
};
