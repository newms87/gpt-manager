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
        Schema::table('agent_thread_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('api_log_id')->nullable()->after('api_response_id');
            $table->foreign('api_log_id')->references('id')->on('api_logs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_thread_messages', function (Blueprint $table) {
            $table->dropForeign(['api_log_id']);
            $table->dropColumn('api_log_id');
        });
    }
};
