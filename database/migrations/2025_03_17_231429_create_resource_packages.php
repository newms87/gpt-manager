<?php

use App\Models\Team\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->uuid()->nullable()->after('id');
        });

        Team::each(fn(Team $team) => $team->forceFill(['uuid' => $team->newUniqueId()])->save());

        Schema::table('teams', function (Blueprint $table) {
            $table->uuid()->unique()->change();
        });

        Schema::create('resource_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_uuid');
            $table->string('resource_type');
            $table->string('resource_id');
            $table->string('name');
            $table->timestamps(3);
            $table->softDeletes();
        });

        Schema::create('resource_package_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_package_id')->constrained('resource_packages')->onDelete('cascade');
            $table->string('version');
            $table->string('version_hash');
            $table->json('definitions');
            $table->timestamps(3);
            $table->softDeletes();
        });

        Schema::create('resource_package_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_uuid')->constrained('teams', 'uuid')->onDelete('cascade');
            $table->foreignUuid('resource_package_id')->constrained('resource_packages')->onDelete('cascade');
            $table->foreignUuid('resource_package_version_id')->nullable()->constrained('resource_package_versions')->onDelete('cascade');
            $table->uuid('source_object_id');
            $table->uuid('local_object_id')->nullable();
            $table->string('object_type');
            $table->timestamps(3);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_package_imports');
        Schema::dropIfExists('resource_package_versions');
        Schema::dropIfExists('resource_packages');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
