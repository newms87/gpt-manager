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
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->integer('error_count')->default(0)->after('active_workers_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn('error_count');
        });
    }
};
