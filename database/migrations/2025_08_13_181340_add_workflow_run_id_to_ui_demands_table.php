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
        Schema::table('ui_demands', function (Blueprint $table) {
            $table->foreignId('workflow_run_id')->nullable()->constrained('workflow_runs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ui_demands', function (Blueprint $table) {
            $table->dropForeign(['workflow_run_id']);
            $table->dropColumn('workflow_run_id');
        });
    }
};
