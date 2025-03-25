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
            $table->dropColumn('fragment_selector');
            $table->foreignId('schema_fragment_id')->nullable()->after('include_json')->constrained('schema_fragments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_artifact_filters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schema_fragment_id');
            $table->string('fragment_selector')->nullable();
        });
    }
};
