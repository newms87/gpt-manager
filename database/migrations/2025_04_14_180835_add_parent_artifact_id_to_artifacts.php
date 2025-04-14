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
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignId('parent_artifact_id')
                ->nullable()
                ->after('id')
                ->constrained('artifacts')
                ->cascadeOnDelete();

            $table->foreignId('team_id')
                ->nullable()
                ->after('id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->unsignedInteger('child_artifacts_count')->after('parent_artifact_id')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropForeign(['parent_artifact_id']);
            $table->dropColumn('parent_artifact_id');
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
            $table->dropColumn('child_artifacts_count');
        });
    }
};
