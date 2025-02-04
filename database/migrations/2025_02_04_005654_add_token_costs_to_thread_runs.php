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
            $table->decimal('input_cost', 10, 4)->default(0)->after('output_tokens');
            $table->decimal('output_cost', 10, 4)->default(0)->after('input_cost');
            $table->decimal('total_cost', 10, 4)->storedAs('input_cost + output_cost')->after('output_cost');
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->decimal('input_cost', 10, 4)->default(0)->after('output_tokens');
            $table->decimal('output_cost', 10, 4)->default(0)->after('input_cost');
            $table->decimal('total_cost', 10, 4)->storedAs('input_cost + output_cost')->after('output_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropColumn('input_cost');
            $table->dropColumn('output_cost');
            $table->dropColumn('total_cost');
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropColumn('input_cost');
            $table->dropColumn('output_cost');
            $table->dropColumn('total_cost');
        });
    }
};
