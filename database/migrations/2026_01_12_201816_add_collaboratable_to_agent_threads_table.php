<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_threads', function (Blueprint $table) {
            $table->string('collaboratable_type')->nullable()->after('agent_id');
            $table->unsignedBigInteger('collaboratable_id')->nullable()->after('collaboratable_type');

            $table->index(['collaboratable_type', 'collaboratable_id'], 'idx_agent_threads_collaboratable');
        });
    }

    public function down(): void
    {
        Schema::table('agent_threads', function (Blueprint $table) {
            $table->dropIndex('idx_agent_threads_collaboratable');
            $table->dropColumn(['collaboratable_type', 'collaboratable_id']);
        });
    }
};
