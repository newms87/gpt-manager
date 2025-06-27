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
        Schema::table('agent_thread_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_thread_messages', 'api_response_id')) {
                $table->string('api_response_id')->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_thread_messages', function (Blueprint $table) {
            if (Schema::hasColumn('agent_thread_messages', 'api_response_id')) {
                $table->dropIndex(['api_response_id']);
                $table->dropColumn('api_response_id');
            }
        });
    }
};
