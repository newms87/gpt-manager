<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->integer('timeout')->nullable()->default(60)->after('api_options');
        });
    }

    public function down(): void
    {
        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->dropColumn('timeout');
        });
    }
};
