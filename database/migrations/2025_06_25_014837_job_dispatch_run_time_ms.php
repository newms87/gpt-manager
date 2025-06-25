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
        Schema::table('job_dispatch', function (Blueprint $table) {
            $table->dropColumn('run_time');
            $table->integer('run_time_ms')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_dispatch', function (Blueprint $table) {
            $table->integer('run_time')->nullable();
            $table->dropColumn('run_time_ms');
        });
    }
};
