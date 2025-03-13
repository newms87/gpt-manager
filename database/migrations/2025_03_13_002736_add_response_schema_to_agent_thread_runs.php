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
            $table->unsignedBigInteger('response_schema_id')->nullable()->after('response_format');
            $table->unsignedBigInteger('response_fragment_id')->nullable()->after('response_schema_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->dropColumn('response_schema_id');
            $table->dropColumn('response_fragment_id');
        });
    }
};
