<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->dropColumn('tools');
            $table->dropColumn('tool_choice');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('tools');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->json('tools')->nullable();
            $table->string('tool_choice')->nullable()->after('tools');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->json('tools')->nullable();
        });
    }
};
