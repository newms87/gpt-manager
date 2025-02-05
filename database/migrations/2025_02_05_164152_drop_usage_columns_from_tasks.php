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
            $table->dropColumn('total_cost');
            $table->dropColumn('input_cost');
            $table->dropColumn('output_cost');
            $table->dropColumn('input_tokens');
            $table->dropColumn('output_tokens');
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->dropColumn('total_cost');
            $table->dropColumn('input_cost');
            $table->dropColumn('output_cost');
            $table->dropColumn('input_tokens');
            $table->dropColumn('output_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table) {
            $table->decimal('total_cost', 10, 4)->storedAs('input_cost+output_cost');
            $table->decimal('input_cost', 10, 4)->default(0);
            $table->decimal('output_cost', 10, 4)->default(0);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->decimal('total_cost', 10, 4)->storedAs('input_cost+output_cost');
            $table->decimal('input_cost', 10, 4)->default(0);
            $table->decimal('output_cost', 10, 4)->default(0);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
        });
    }
};
