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
            $table->json('json_schema_config')->nullable()->after('response_fragment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->dropColumn('json_schema_config');
        });
    }
};
