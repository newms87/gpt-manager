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
        Schema::table('resource_package_imports', function (Blueprint $table) {
            $table->boolean('can_view')->default(false)->after('object_type');
            $table->boolean('can_edit')->default(false)->after('can_view');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_package_imports', function (Blueprint $table) {
            $table->dropColumn('can_view');
            $table->dropColumn('can_edit');
        });
    }
};
