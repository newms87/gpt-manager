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
        Schema::table('task_artifact_filters', function (Blueprint $table) {
            $table->foreignUuid('resource_package_import_id')->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_artifact_filters', function (Blueprint $table) {
            $table->dropColumn('resource_package_import_id');
        });
    }
};
