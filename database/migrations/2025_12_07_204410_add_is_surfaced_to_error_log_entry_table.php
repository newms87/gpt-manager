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
            $table->boolean('is_surfaced')->default(false)->after('data');
            $table->index(['audit_request_id', 'is_surfaced']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('error_log_entry', function (Blueprint $table) {
            $table->dropIndex(['audit_request_id', 'is_surfaced']);
            $table->dropColumn('is_surfaced');
        });
    }
};
