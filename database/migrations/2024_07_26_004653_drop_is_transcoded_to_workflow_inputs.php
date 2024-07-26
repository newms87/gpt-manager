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
        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->dropColumn('is_transcoded');
        });

        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->dropColumn('use_input');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->boolean('is_transcoded')->default(false);
        });

        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->boolean('use_input')->default(false);
        });
    }
};
