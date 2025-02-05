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
        Schema::table('task_runs', function (Blueprint $table) {
            $table->string('name')->after('status');
            $table->string('step')->default('Initial')->after('name');
            $table->decimal('percent_complete', 5, 2)->default(0)->after('step');
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->string('name')->after('status');
            $table->string('activity')->default('Initializing')->after('name');
            $table->decimal('percent_complete', 5, 2)->default(0)->after('activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('step');
            $table->dropColumn('percent_complete');
        });
        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('activity');
            $table->dropColumn('percent_complete');
        });
    }
};
