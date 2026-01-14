<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->unsignedInteger('building_job_dispatch_id')->nullable()->after('preview_stored_file_id');
            $table->json('pending_build_context')->nullable()->after('building_job_dispatch_id');

            $table->foreign('building_job_dispatch_id')->references('id')->on('job_dispatch')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->dropForeign(['building_job_dispatch_id']);
            $table->dropColumn(['building_job_dispatch_id', 'pending_build_context']);
        });
    }
};
