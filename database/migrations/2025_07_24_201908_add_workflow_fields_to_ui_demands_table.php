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
        Schema::table('ui_demands', function (Blueprint $table) {
            $table->foreignId('team_object_id')->nullable()->after('metadata')
                ->constrained('team_objects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ui_demands', function (Blueprint $table) {
            $table->dropForeign(['team_object_id']);
            $table->dropColumn('team_object_id');
        });
    }
};
