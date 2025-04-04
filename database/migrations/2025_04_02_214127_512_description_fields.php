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
            $table->string('description', 512)->nullable()->change();
        });

        Schema::table('schema_definitions', function (Blueprint $table) {
            $table->string('description', 512)->nullable()->change();
        });

        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->string('description', 512)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
