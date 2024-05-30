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
        Schema::rename('input_sources', 'workflow_inputs');

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->renameColumn('input_source_id', 'workflow_input_id');
        });

        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->renameColumn('use_input_source', 'use_input');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('workflow_inputs', 'input_sources');

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->renameColumn('workflow_input_id', 'input_source_id');
        });

        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->renameColumn('use_input', 'use_input_source');
        });
    }
};
