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
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('max_workers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->integer('max_workers')->default(10);
        });
    }
};
