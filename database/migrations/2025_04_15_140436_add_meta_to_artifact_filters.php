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
            $table->boolean('include_meta')->after('include_json');
            $table->json('meta_fragment_selector')->nullable()->after('schema_fragment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_artifact_filters', function (Blueprint $table) {
            $table->dropColumn('include_meta');
            $table->dropColumn('meta_fragment_selector');
        });
    }
};
