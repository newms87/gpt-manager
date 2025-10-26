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
        Schema::table('resource_packages', function (Blueprint $table) {
            $table->renameColumn('team_uuid', 'creator_team_uuid');
        });

        Schema::table('resource_package_imports', function (Blueprint $table) {
            $table->renameColumn('team_uuid', 'creator_team_uuid');
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_packages', function (Blueprint $table) {
            $table->renameColumn('creator_team_uuid', 'team_uuid');
        });

        Schema::table('resource_package_imports', function (Blueprint $table) {
            $table->renameColumn('creator_team_uuid', 'team_uuid');
            $table->dropColumn('team_id');
        });
    }
};
