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
        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->unsignedInteger('timeout_after')->default(600)->after('description');
            $table->unsignedInteger('max_attempts')->nullable(1)->after('timeout_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->dropColumn('timeout_after');
            $table->dropColumn('max_attempts');
        });
    }
};
