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
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->string('grouping_mode')->default('Concatenate')->after('task_runner_class');
            $table->boolean('split_by_file')->default(0)->after('grouping_mode');
            $table->dropColumn('input_grouping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('grouping_mode');
            $table->dropColumn('split_by_file');
            $table->string('input_grouping');
        });
    }
};
