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
        Schema::table('error_log_entry', function (Blueprint $table) {
            // Drop the old index
            $table->dropIndex(['audit_request_id', 'is_surfaced']);

            // Rename the column
            $table->renameColumn('is_surfaced', 'is_retryable');
        });

        // Create the new index after the column rename
        Schema::table('error_log_entry', function (Blueprint $table) {
            $table->index(['audit_request_id', 'is_retryable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('error_log_entry', function (Blueprint $table) {
            // Drop the new index
            $table->dropIndex(['audit_request_id', 'is_retryable']);

            // Rename the column back
            $table->renameColumn('is_retryable', 'is_surfaced');
        });

        // Recreate the old index
        Schema::table('error_log_entry', function (Blueprint $table) {
            $table->index(['audit_request_id', 'is_surfaced']);
        });
    }
};
