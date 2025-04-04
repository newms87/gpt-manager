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
        if (Schema::hasColumn('task_definition_directives', 'resource_package_import_id')) {
            return;
        }

        Schema::table('task_definition_directives', function (Blueprint $table) {
            $table->renameColumn('resource_package_import', 'resource_package_import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
