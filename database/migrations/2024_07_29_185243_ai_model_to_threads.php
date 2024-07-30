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
        Schema::table('thread_runs', function (Blueprint $table) {
            $table->string('agent_model')->nullable()->after('refreshed_at');
            $table->string('total_cost')->nullable()->after('agent_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thread_runs', function (Blueprint $table) {
            $table->dropColumn('agent_model');
            $table->dropColumn('total_cost');
        });
    }
};
