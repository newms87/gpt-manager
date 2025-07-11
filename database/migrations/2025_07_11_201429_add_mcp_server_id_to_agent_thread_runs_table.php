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
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->constrained('mcp_servers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->dropForeign(['mcp_server_id']);
            $table->dropColumn('mcp_server_id');
        });
    }
};
