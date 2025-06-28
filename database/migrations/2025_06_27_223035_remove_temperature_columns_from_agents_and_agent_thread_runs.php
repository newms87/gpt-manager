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
        // Remove temperature column from agents table
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('temperature');
        });

        // Remove temperature column from agent_thread_runs table
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->dropColumn('temperature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back temperature column to agents table
        Schema::table('agents', function (Blueprint $table) {
            $table->float('temperature')->default(0.7);
        });

        // Add back temperature column to agent_thread_runs table
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->float('temperature')->default(0.7);
        });
    }
};
