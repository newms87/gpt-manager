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
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->unsignedInteger('template_variables_count')->default(0);
            $table->unsignedInteger('job_dispatches_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->dropColumn('template_variables_count');
            $table->dropColumn('job_dispatches_count');
        });
    }
};
